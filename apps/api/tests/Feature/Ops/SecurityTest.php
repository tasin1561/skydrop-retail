<?php

namespace Tests\Feature\Ops;

use App\Models\ActivityLog;
use App\Models\BlockedEntry;
use App\Services\Security\AppSignature;
use App\Services\Security\Blocklist;
use Filament\Facades\Filament;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityTest extends TestCase
{
    use OpsTestHelpers, RefreshDatabase;

    private const UA = ['User-Agent' => 'Mozilla/5.0 (Linux; Android 14) SkydropRetail/1.0', 'Accept-Language' => 'en-BD,en;q=0.9'];

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'security.enforce' => true,
            'security.internal_key' => 'internal-test-key',
            'security.app_keys' => ['mobile' => 'app-secret'],
            'retail.otp.driver' => 'log', 'retail.sms.driver' => 'log',
        ]);
    }

    /** @return array<string, string> */
    private function signed(string $method, string $path, string $query = '', ?int $ts = null, string $secret = 'app-secret'): array
    {
        $ts = (string) ($ts ?? time());

        return self::UA + ['X-App-Key' => 'mobile', 'X-App-Timestamp' => $ts,
            'X-App-Signature' => AppSignature::sign($secret, $ts, $method, '/api/v1'.$path, $query)];
    }

    public function test_catalog_guard_rejects_unsigned_and_accepts_internal_and_signed(): void
    {
        $this->getJson('/api/v1/marketplaces', self::UA)->assertStatus(401)->assertJsonPath('code', 'unsigned');
        $this->getJson('/api/v1/marketplaces', self::UA + ['X-Internal-Key' => 'wrong'])->assertStatus(401);

        $this->getJson('/api/v1/marketplaces', ['X-Internal-Key' => 'internal-test-key'])->assertOk();
        $this->getJson('/api/v1/suggest?q=ear', $this->signed('GET', '/suggest', 'q=ear'))->assertOk();

        // Tampered query, wrong secret, stale timestamp.
        $this->getJson('/api/v1/suggest?q=other', $this->signed('GET', '/suggest', 'q=ear'))->assertStatus(401);
        $this->getJson('/api/v1/suggest?q=ear', $this->signed('GET', '/suggest', 'q=ear', null, 'nope'))->assertStatus(401);
        $this->getJson('/api/v1/suggest?q=ear', $this->signed('GET', '/suggest', 'q=ear', time() - 600))->assertStatus(401);

        // Non-catalog endpoints stay public; security headers everywhere.
        $this->getJson('/api/v1/health')->assertOk()->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('X-Frame-Options', 'DENY');
    }

    public function test_page_cap(): void
    {
        $this->getJson('/api/v1/search?q=x&page=21', ['X-Internal-Key' => 'internal-test-key'])->assertStatus(422)->assertJsonPath('code', 'page_limit');
    }

    public function test_strict_rate_limit_returns_429(): void
    {
        config(['security.limits.strict_ip' => 2]);
        foreach ([1, 2] as $i) {
            $this->getJson('/api/v1/suggest?q=ab', $this->signed('GET', '/suggest', 'q=ab'))->assertOk();
        }
        $this->getJson('/api/v1/suggest?q=ab', $this->signed('GET', '/suggest', 'q=ab'))
            ->assertStatus(429)->assertJsonPath('code', 'rate_limited')->assertHeader('Retry-After');
        // The Next.js server has its own (high) bucket.
        $this->getJson('/api/v1/suggest?q=ab', ['X-Internal-Key' => 'internal-test-key'])->assertOk();
    }

    public function test_too_many_distinct_products_triggers_challenge(): void
    {
        config(['security.bot.distinct_products_per_minute' => 2]);
        foreach (['B0BOT00001', 'B0BOT00002'] as $id) {
            $this->assertNotSame(403, $this->getJson("/api/v1/products/{$id}/related", $this->signed('GET', "/products/{$id}/related"))->status());
        }
        $this->getJson('/api/v1/products/B0BOT00003/related', $this->signed('GET', '/products/B0BOT00003/related'))
            ->assertStatus(403)->assertJsonPath('code', 'challenge');
        $this->assertTrue(ActivityLog::query()->where('action', 'security.bot')->exists());
    }

    public function test_honeypot_blocks_the_ip_for_a_day(): void
    {
        $this->getJson('/api/v1/products/export', self::UA)->assertNotFound();
        $entry = BlockedEntry::query()->first();
        $this->assertSame('ip', $entry->type);
        $this->assertSame('honeypot', $entry->source);
        $this->assertTrue($entry->expires_at->between(now()->addHours(23), now()->addHours(25)));
        $this->assertTrue(ActivityLog::query()->where('action', 'security.honeypot')->exists());

        $this->getJson('/api/v1/health')->assertStatus(403)->assertJsonPath('code', 'blocked');
        $this->getJson('/api/v1/marketplaces', ['X-Internal-Key' => 'internal-test-key'])->assertStatus(403);
    }

    public function test_honeypot_via_next_server_blocks_the_forwarded_ip(): void
    {
        $this->getJson('/api/v1/products/export', ['X-Internal-Key' => 'internal-test-key', 'X-Client-IP' => '203.0.113.9'])->assertNotFound();
        $this->assertTrue(Blocklist::isBlocked('ip', '203.0.113.9'));
        $this->assertFalse(Blocklist::isBlocked('ip', '127.0.0.1'));
    }

    public function test_otp_cooldown_and_lockout_after_five_wrong_codes(): void
    {
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '01755000000'])->assertOk();
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '01755000000'])->assertStatus(429); // cooldown

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01755000000', 'code' => '000000', 'name' => 'X'])->assertStatus(422);
        }
        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01755000000', 'code' => '000000'])->assertStatus(429)->assertJsonPath('code', 'rate_limited');
        RateLimiter::clear('otp:cooldown:+8801755000000');
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '01755000000'])->assertStatus(429);
    }

    public function test_turnstile_required_on_otp_request_when_configured(): void
    {
        config(['security.turnstile.secret' => 'ts-secret', 'security.turnstile.site_key' => 'ts-site']);
        Http::fake(['challenges.cloudflare.com/*' => fn ($req) => Http::response(['success' => $req['response'] === 'good'])]);
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '01766000000'])->assertStatus(403)->assertJsonPath('turnstileSiteKey', 'ts-site');
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '01766000000', 'turnstileToken' => 'bad'])->assertStatus(403);
        $this->postJson('/api/v1/auth/otp/request', ['phone' => '01766000000', 'turnstileToken' => 'good'])->assertOk();
    }

    public function test_admin_2fa_required_for_admin(): void
    {
        config(['security.admin.require_2fa' => true]);
        $admin = $this->staff();
        $this->actingAs($admin)->get('/admin')->assertRedirect(Filament::getPanel('admin')->getSetUpRequiredMultiFactorAuthenticationUrl());

        $admin->saveAppAuthenticationSecret('JBSWY3DPEHPK3PXP');
        $this->assertNotSame('JBSWY3DPEHPK3PXP', DB::table('users')->where('id', $admin->id)->value('app_authentication_secret'));
        $this->actingAs($admin->refresh())->get('/admin')->assertOk();
    }

    public function test_qr_endpoint_only_encodes_own_links(): void
    {
        config(['retail.web_url' => 'https://retail.skydrop.online']);
        $this->get('/api/v1/qr?text='.urlencode('https://retail.skydrop.online/p/B0X'))->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
        $this->get('/api/v1/qr?text='.urlencode('https://evil.example/x'), ['Accept' => 'application/json'])->assertStatus(422);
    }
}

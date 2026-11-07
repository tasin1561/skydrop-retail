<?php

namespace Tests\Feature\Security;

use App\Http\Controllers\Api\Commerce\OrderController;
use App\Models\Product;
use App\Services\Commerce\Verify\LocalPriceVerifier;
use App\Services\Commerce\Verify\PriceVerifier;
use App\Services\Security\Blocklist;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/** Coordinator follow-up (web audit): internal calls rate-limited per forwarded shopper IP, strict returnUrl, safe honeypot. */
class InternalClientIpTest extends TestCase
{
    use RefreshDatabase;

    private const KEY = ['X-Internal-Key' => 'internal-test-key'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['security.enforce' => true, 'security.internal_key' => 'internal-test-key']);
        $this->app->bind(PriceVerifier::class, LocalPriceVerifier::class);
        Product::query()->create(['id' => 'B0IPTEST01', 'market' => 'amazon', 'category' => 'electronics', 'title' => 'Item',
            'price_inr' => 1000, 'mrp_inr' => 1200, 'in_stock' => true]);
    }

    private function verify(array $headers)
    {
        return $this->postJson('/api/v1/checkout/verify', ['items' => [['productId' => 'B0IPTEST01', 'qty' => 1]]], self::KEY + $headers);
    }

    public function test_internal_route_throttle_is_per_forwarded_shopper_ip(): void
    {
        // checkout/verify: throttle:20,1. One shopper behind the Next.js server hits the limit; another shopper does not.
        foreach (range(1, 20) as $_) {
            $this->verify(['X-Client-IP' => '203.0.113.10'])->assertOk();
        }
        $this->verify(['X-Client-IP' => '203.0.113.10'])->assertStatus(429);
        $this->verify(['X-Client-IP' => '198.51.100.20'])->assertOk();
    }

    public function test_internal_calls_without_a_usable_client_ip_share_one_bucket(): void
    {
        // Missing or private X-Client-IP: shared server bucket with a 20x ceiling (400 for throttle:20,1), not unlimited.
        foreach (range(1, 400) as $i) {
            $this->verify($i % 2 ? [] : ['X-Client-IP' => '10.0.0.5'])->assertOk();
        }
        $this->verify([])->assertStatus(429);
        $this->verify(['X-Client-IP' => '203.0.113.10'])->assertOk(); // real shoppers keep their own buckets
    }

    public function test_internal_catalog_strict_limits_apply_per_shopper(): void
    {
        config(['security.limits.internal' => 1000]);
        $hits = 0;
        do {
            $status = $this->getJson('/api/v1/search?q=item', self::KEY + ['X-Client-IP' => '203.0.113.30'])->status();
            $hits++;
        } while ($status !== 429 && $hits < 500);
        $this->assertSame(429, $status, 'per-shopper search limit applies to Next.js calls');
        $this->assertNotSame(429, $this->getJson('/api/v1/search?q=item', self::KEY + ['X-Client-IP' => '203.0.113.31'])->status());
    }

    public function test_honeypot_only_blocks_public_forwarded_ips(): void
    {
        foreach ([[], ['X-Client-IP' => ''], ['X-Client-IP' => '127.0.0.1'], ['X-Client-IP' => '10.1.2.3'], ['X-Client-IP' => '::1'], ['X-Client-IP' => 'not-an-ip']] as $h) {
            $this->getJson('/api/v1/products/export', self::KEY + $h)->assertNotFound();
        }
        foreach (['127.0.0.1', '10.1.2.3', '::1'] as $ip) {
            $this->assertFalse(Blocklist::isBlocked('ip', $ip));
        }
        $this->getJson('/api/v1/marketplaces', self::KEY)->assertOk(); // the storefront server is still served

        $this->getJson('/api/v1/products/export', self::KEY + ['X-Client-IP' => '203.0.113.99'])->assertNotFound();
        $this->assertTrue(Blocklist::isBlocked('ip', '203.0.113.99'));
        $this->getJson('/api/v1/marketplaces', self::KEY + ['X-Client-IP' => '203.0.113.99'])->assertStatus(403); // the shopper, not the server
        $this->getJson('/api/v1/marketplaces', self::KEY)->assertOk();
    }

    public function test_return_url_is_a_strict_allowlist(): void
    {
        config(['retail.web_url' => 'https://retail.skydrop.online']);
        foreach (['https://retail.skydrop.online/checkout/success?order=1', 'skydropretail://checkout/success'] as $ok) {
            $this->assertTrue(OrderController::allowedReturnUrl($ok), $ok);
        }
        foreach (['http://retail.skydrop.online/a', 'https://retail.skydrop.online:8443/a', 'https://retail.skydrop.online@evil.com/',
            'https://evil.com\\@retail.skydrop.online/', 'https://u:p@retail.skydrop.online/', 'https://retail.skydrop.online.evil.com/',
            '//retail.skydrop.online/x', 'skydropretail:checkout', 'javascript://retail.skydrop.online/%0aalert(1)',
            "https://retail.skydrop.online/\n", 'ftp://retail.skydrop.online/'] as $bad) {
            $this->assertFalse(OrderController::allowedReturnUrl($bad), $bad);
        }
    }
}

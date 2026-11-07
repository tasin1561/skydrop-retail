<?php

namespace Tests\Feature\System;

use App\Filament\Pages\SystemHealth;
use App\Filament\Pages\SystemLimits;
use App\Filament\Pages\SystemSettings;
use App\Models\ActivityLog;
use App\Models\ApiLog;
use App\Models\ErrorEvent;
use App\Models\ProviderHealth;
use App\Models\Role;
use App\Models\User;
use App\Services\System\ErrorRecorder;
use App\Services\System\HealthAlerts;
use App\Services\System\Heartbeat;
use App\Services\System\LogReader;
use App\Services\System\Maintenance;
use App\Support\Limits;
use App\Support\Rbac\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        @unlink(HealthAlerts::stateFile());
    }

    protected function tearDown(): void
    {
        @unlink(HealthAlerts::stateFile());
        parent::tearDown();
    }

    private function staff(?string $role): User
    {
        $user = new User;
        $user->forceFill(['name' => 'Staff '.uniqid(), 'email' => uniqid().'@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'support'])->save();
        if ($role) {
            $user->roles()->sync([Role::query()->where('slug', $role)->value('id')]);
        }
        app(PermissionResolver::class)->flush();

        return $user;
    }

    public function test_liveness_and_ready_with_fresh_and_stale_heartbeat(): void
    {
        $this->getJson('/api/v1/health')->assertOk()->assertJsonPath('ok', true);

        config(['queue.default' => 'database']);
        $this->getJson('/api/v1/health/ready')->assertStatus(503)->assertJsonPath('checks.queue', false)->assertJsonPath('checks.database', true);

        Heartbeat::beat(Heartbeat::WORKER);
        $this->getJson('/api/v1/health/ready')->assertOk()->assertJsonPath('ok', true);

        DB::table('system_heartbeats')->where('name', 'worker')->update(['beat_at' => now()->subMinutes(4)]);
        $this->getJson('/api/v1/health/ready')->assertStatus(503)->assertJsonPath('ok', false)->assertJsonPath('checks.cache', true);

        // Sync queue: no worker needed.
        config(['queue.default' => 'sync']);
        $this->getJson('/api/v1/health/ready')->assertOk();
    }

    public function test_details_needs_the_health_token(): void
    {
        $this->getJson('/api/v1/health/details')->assertNotFound(); // HEALTH_TOKEN unset: disabled
        config(['system.health_token' => 'tok-123456']);
        $this->getJson('/api/v1/health/details')->assertStatus(401);
        $this->getJson('/api/v1/health/details', ['X-Health-Token' => 'wrong'])->assertStatus(401);
        $r = $this->getJson('/api/v1/health/details', ['X-Health-Token' => 'tok-123456']);
        $this->assertContains($r->status(), [200, 503]);
        $r->assertJsonStructure(['status', 'generatedAt', 'cards' => ['app', 'database', 'cache', 'queue', 'providers', 'fetchJobs', 'messaging',
            'payments', 'storage', 'security', 'features', 'errors']]);
        $this->assertSame('green', $r->json('cards.database.status'));
        $this->getJson('/api/v1/health/details', ['Authorization' => 'Bearer tok-123456'])->assertJsonStructure(['cards']);
    }

    public function test_health_check_alerts_once_per_state_change_and_on_recovery(): void
    {
        config(['retail.alerts.telegram_bot' => 'bot-token', 'retail.alerts.telegram_chat' => '42', 'queue.default' => 'database']);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Heartbeat::beat(Heartbeat::WORKER);
        ProviderHealth::query()->create(['provider' => 'oxylabs:amazon', 'state' => 'open', 'fail_count' => 5, 'opened_at' => now()->subMinutes(20)]);

        $this->artisan('retail:health-check')->assertSuccessful();
        $this->artisan('retail:health-check')->assertSuccessful(); // same state: no second alert
        $circuit = fn () => Http::recorded(fn ($req) => str_contains((string) $req['text'], 'Provider circuit open') && ! str_contains((string) $req['text'], 'Recovered'));
        $this->assertCount(1, $circuit());
        $this->assertSame('42', (string) $circuit()[0][0]['chat_id']);

        // Daily cap: a second, independent condition.
        Limits::set('catalog.oxylabs_daily_usd', 1);
        ApiLog::query()->create(['provider' => 'oxylabs', 'type' => 'product', 'status' => 200, 'ms' => 1, 'cost_usd' => 2, 'created_at' => now()]);
        $this->artisan('retail:health-check');
        $this->assertCount(1, Http::recorded(fn ($req) => str_contains((string) $req['text'], 'daily cap reached')));

        // Recovery is announced once.
        ProviderHealth::query()->update(['state' => 'closed', 'opened_at' => null]);
        $this->artisan('retail:health-check');
        $this->artisan('retail:health-check');
        $this->assertCount(1, Http::recorded(fn ($req) => str_contains((string) $req['text'], 'Recovered: Provider circuit open')));
        $this->assertCount(1, $circuit());
        $this->assertFalse(app(HealthAlerts::class)->state()['circuit_open']);
        $this->assertTrue(app(HealthAlerts::class)->state()['daily_cap_hit']);
    }

    public function test_worker_stale_alert(): void
    {
        config(['queue.default' => 'database']);
        $r = app(HealthAlerts::class)->run();
        $this->assertContains('worker_stale', $r['alerts']);
        Heartbeat::beat(Heartbeat::WORKER);
        $r = app(HealthAlerts::class)->run();
        $this->assertContains('worker_stale', $r['recoveries']);
    }

    public function test_maintenance_flag_in_features(): void
    {
        $this->getJson('/api/v1/features')->assertOk()->assertJsonPath('maintenance.on', false)->assertJsonStructure(['features' => ['checkout'], 'maintenance' => ['on', 'message']]);
        Maintenance::set(true, 'Back at 5 pm.');
        $this->getJson('/api/v1/features')->assertJsonPath('maintenance.on', true)->assertJsonPath('maintenance.message', 'Back at 5 pm.');
        Maintenance::set(false);
        $this->getJson('/api/v1/features')->assertJsonPath('maintenance.on', false);
    }

    public function test_error_events_are_grouped_and_throttled(): void
    {
        $e = new RuntimeException('Boom token=supersecretvalue');
        ErrorRecorder::record($e);
        ErrorRecorder::record($e); // throttled: counted in the cache
        $this->assertSame(1, ErrorEvent::query()->count());
        $row = ErrorEvent::query()->first();
        $this->assertSame(1, $row->count);
        $this->assertStringNotContainsString('supersecretvalue', $row->message);

        cache()->forget('error_events:'.ErrorRecorder::fingerprint($e)); // next minute
        ErrorRecorder::record($e);
        $this->assertSame(3, ErrorEvent::query()->first()->count);

        report(new RuntimeException('reported through the handler'));
        $this->assertSame(2, ErrorEvent::query()->count());
    }

    public function test_log_masking(): void
    {
        config(['retail.oxylabs.token' => 'oxy-secret-token-value']);
        $masked = LogReader::mask('call with oxy-secret-token-value and Authorization: Bearer abcdefghijkl password=hunter22');
        $this->assertStringNotContainsString('oxy-secret-token-value', $masked);
        $this->assertStringNotContainsString('abcdefghijkl', $masked);
        $this->assertStringNotContainsString('hunter22', $masked);
    }

    public function test_admin_pages_render_for_super_admin(): void
    {
        $this->actingAs($this->staff('super_admin'));
        $this->get(SystemLimits::getUrl())->assertOk()->assertSee('Oxylabs daily cap')->assertSee('Max quantity per item');
        $this->get(SystemHealth::getUrl())->assertOk()->assertSee('Database')->assertSee('wire:poll.30s', false);
        $this->get(SystemSettings::getUrl())->assertOk()->assertSee('Maintenance mode');

        Livewire::test(SystemLimits::class)
            ->set('data.orders__max_qty', 7)
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame(7, Limits::get('orders.max_qty'));
        $this->assertTrue(ActivityLog::query()->where('action', 'limits.updated')->exists());

        Livewire::test(SystemLimits::class)->call('resetLimit', 'orders.max_qty')->assertSet('data.orders__max_qty', 20);
        $this->assertSame(20, Limits::get('orders.max_qty'));

        Livewire::test(SystemSettings::class)->set('data.maintenance_on', true)->set('data.maintenance_message', 'Upgrading')->call('save');
        $this->assertTrue(Maintenance::state()['on']);
        $this->assertSame('Upgrading', Maintenance::state()['message']);
        Livewire::test(SystemSettings::class)->call('clearCache', 'features')->call('clearCache', 'settings')->call('clearCache', 'catalog');

        ProviderHealth::query()->create(['provider' => 'oxylabs:amazon', 'state' => 'open', 'fail_count' => 5, 'opened_at' => now()]);
        Livewire::test(SystemHealth::class)->assertSee('oxylabs:amazon')->call('resetCircuit', 'oxylabs:amazon');
        $this->assertSame('closed', ProviderHealth::query()->find('oxylabs:amazon')->state);
    }

    public function test_admin_pages_are_403_without_permission(): void
    {
        $this->actingAs($this->staff('support'));
        $this->get(SystemLimits::getUrl())->assertForbidden();
        $this->get(SystemHealth::getUrl())->assertForbidden();
        $this->get(SystemSettings::getUrl())->assertForbidden();
    }
}

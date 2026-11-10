<?php

namespace Tests\Feature\System;

use App\Filament\Pages\DataUsage as DataUsagePage;
use App\Filament\Support\Ops;
use App\Models\ApiLog;
use App\Models\Product;
use App\Models\Role;
use App\Models\User;
use App\Services\Alerts;
use App\Services\Catalog\CatalogService;
use App\Services\Catalog\DataUsage;
use App\Support\Limits;
use App\Support\Rbac\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Mockery;
use Tests\Feature\Catalog\CatalogTestHelpers;
use Tests\TestCase;

class DataUsageTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /** One api_logs row as the logger would write it. */
    private function logCall(string $market, bool $ok = true, string $purpose = 'product', bool $retry = false, ?Carbon $at = null, int $times = 1, string $provider = 'oxylabs'): void
    {
        $rows = [];
        for ($i = 0; $i < $times; $i++) {
            $rows[] = ['provider' => $provider, 'type' => 'product', 'purpose' => $purpose, 'market' => $market, 'status' => $ok ? 200 : 503,
                'success' => $ok, 'is_retry' => $retry, 'ms' => 1, 'cost_usd' => 0, 'created_at' => ($at ?? now())->copy()->utc()];
        }
        foreach (array_chunk($rows, 500) as $chunk) {
            ApiLog::query()->insert($chunk);
        }
    }

    private function staff(?string $role, ?string $legacy = null): User
    {
        $user = new User;
        $user->forceFill(['name' => 'Staff '.uniqid(), 'email' => uniqid().'@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => $legacy ?? 'support'])->save();
        if ($role) {
            $user->roles()->sync(Role::query()->where('slug', $role)->pluck('id')->all());
        }
        app(PermissionResolver::class)->flush();

        return $user;
    }

    private function as(User $user): static
    {
        $this->flushSession();
        app('auth')->forgetGuards();
        app(PermissionResolver::class)->flush();

        return $this->actingAs($user);
    }

    public function test_limits_defaults(): void
    {
        $this->assertSame(220000, Limits::int('data.capacity_amazon_calls'));
        $this->assertSame(110000, Limits::int('data.capacity_flipkart_calls'));
        $this->assertSame(80, Limits::int('data.capacity_alert_percent'));
        $this->assertSame(1, Limits::int('data.cycle_start_day'));
        Limits::set('data.cycle_start_day', 31);
        $this->assertSame(28, Limits::int('data.cycle_start_day')); // clamped so every month has the day
    }

    public function test_live_calls_record_purpose_success_and_retry(): void
    {
        $this->seedCatalog();
        $this->goLive('amazon');
        config(['retail.variants.mode' => 'manual']);
        $this->fakeOxylabs();

        // Pasted link → queued product fetch (sync queue in tests).
        $this->postJson('/api/v1/products/resolve', ['url' => 'amazon.in/gp/product/B0ABCDEFGH'])->assertStatus(202);
        $row = ApiLog::query()->where('provider', '!=', 'cache')->latest('id')->first();
        $this->assertSame('paste-link', $row->purpose);
        $this->assertSame('amazon', $row->market);
        $this->assertTrue($row->success);
        $this->assertFalse($row->is_retry);

        // Staff refresh from the admin.
        Ops::refreshProduct('B0ABCDEFGH');
        $this->assertSame('refresh', ApiLog::query()->latest('id')->value('purpose'));

        // Plain product refresh (product page job).
        app(CatalogService::class)->refreshProduct('B0ABCDEFGH');
        $this->assertSame('product', ApiLog::query()->latest('id')->value('purpose'));

        // Checkout verification is always "verify".
        app(CatalogService::class)->verify([['productId' => 'B0ABCDEFGH', 'qty' => 1, 'unitBdt' => 1]]);
        $this->assertSame('verify', ApiLog::query()->latest('id')->value('purpose'));
    }

    public function test_failed_call_then_fallback_is_logged_as_failure_plus_successful_retry(): void
    {
        $this->seedCatalog();
        $this->goLive('amazon');
        config(['retail.fallbacks.openwebninja.key' => 'rapid']);
        Http::fake([
            'realtime.oxylabs.io/*' => Http::response('down', 503),
            'real-time-amazon-data.p.rapidapi.com/*' => Http::response(['data' => [
                'product_title' => 'Buds', 'product_price' => '₹1,799', 'product_photos' => ['https://m.media-amazon.com/a.jpg'], 'product_availability' => 'In stock',
            ]]),
        ]);
        app(CatalogService::class)->refreshProduct('B0FMDLD86P');

        $rows = ApiLog::query()->orderBy('id')->get();
        $this->assertCount(2, $rows);
        $this->assertFalse($rows[0]->success);
        $this->assertFalse($rows[0]->is_retry);
        $this->assertTrue($rows[1]->success);
        $this->assertTrue($rows[1]->is_retry);
        $this->assertSame(['product', 'product'], $rows->pluck('purpose')->all());

        $r = app(DataUsage::class)->report();
        $this->assertSame(1, $r['ok']['amazon']);
        $this->assertSame(1, $r['failed_total']);
        $this->assertSame(1, $r['retries']);
        $this->assertSame(1, $r['retries_ok']);
    }

    public function test_only_successful_calls_count_and_cache_hits_are_ignored(): void
    {
        $this->logCall('amazon', true, times: 10);
        $this->logCall('amazon', false, times: 7);
        $this->logCall('flipkart', false, times: 3);
        $this->logCall('amazon', true, provider: 'cache', times: 50);
        ApiLog::query()->insert(['provider' => 'cache', 'type' => 'cache_hit', 'market' => 'amazon', 'status' => 200, 'ms' => 0, 'cost_usd' => 0, 'created_at' => now()]);

        $r = app(DataUsage::class)->report();
        $this->assertSame(10, $r['ok']['amazon']);
        $this->assertSame(0, $r['ok']['flipkart']);
        $this->assertSame(10, $r['failed_total']);
        $this->assertEquals(10.0, $r['used_units']);
        $this->assertEqualsWithDelta(10 / 220000 * 100, $r['pct'], 1e-9);
    }

    public function test_mixed_pool_math_in_amazon_units(): void
    {
        $this->logCall('amazon', true, times: 2200);
        $this->logCall('flipkart', true, times: 1100);
        $this->logCall('bigbasket', true, times: 5); // outside the plan

        $r = app(DataUsage::class)->report();
        // 1,100 Flipkart calls × (220000 / 110000) = 2,200 Amazon-call units.
        $this->assertEqualsWithDelta(4400.0, $r['used_units'], 1e-6);
        $this->assertEqualsWithDelta(2.0, $r['pct'], 1e-9);
        $this->assertSame(215600, $r['remaining_amazon']);
        $this->assertSame((int) floor(215600 / (220000 / 110000)), $r['remaining_flipkart']);
        $this->assertSame(107800, $r['remaining_flipkart']);
        $this->assertSame(5, $r['ok']['other']);

        // 100% of either side alone fills the pool.
        $u = app(DataUsage::class);
        $this->assertEqualsWithDelta(220000.0, $u->units(0, 110000), 1e-6);
        $this->assertEqualsWithDelta(220000.0, $u->units(220000, 0), 1e-6);
    }

    public function test_projection_uses_the_daily_rate_so_far(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-11-11 00:00', 'Asia/Dhaka')); // 10 days into a 30-day cycle
        $this->logCall('amazon', true, at: Carbon::parse('2026-11-05 12:00', 'Asia/Dhaka'), times: 1000);

        $r = app(DataUsage::class)->report();
        $this->assertEqualsWithDelta(100.0, $r['per_day_units'], 1e-6);
        $this->assertEqualsWithDelta(3000.0, $r['projected_units'], 1e-6);
        $this->assertArrayHasKey('2026-11-05', $r['days']);
        $this->assertSame(1000, $r['days']['2026-11-05']['amazon']);
        $this->assertCount(30, $r['days']);
    }

    public function test_cycle_boundaries_follow_start_day_in_bangladesh_time(): void
    {
        Limits::set('data.cycle_start_day', 15);
        Carbon::setTestNow(Carbon::parse('2026-11-20 10:00', 'Asia/Dhaka'));
        $usage = app(DataUsage::class);

        $c = $usage->cycle();
        $this->assertSame('2026-11', $c['key']);
        $this->assertSame('2026-11-14 18:00:00', $c['start']->toDateTimeString()); // 15 Nov 00:00 Dhaka in UTC
        $this->assertSame('2026-12-14 18:00:00', $c['end']->toDateTimeString());
        $this->assertSame('15 Nov 2026 – 14 Dec 2026', $c['label']);

        // Before the start day, the cycle began last month.
        $this->assertSame('2026-10', $usage->cycle(Carbon::parse('2026-11-14 23:59', 'Asia/Dhaka'))['key']);

        $this->logCall('amazon', true, at: Carbon::parse('2026-11-14 23:59', 'Asia/Dhaka'), times: 3); // previous cycle
        $this->logCall('amazon', true, at: Carbon::parse('2026-11-15 00:00', 'Asia/Dhaka'), times: 4); // first second of this one
        $this->logCall('amazon', true, at: Carbon::parse('2026-12-15 00:00', 'Asia/Dhaka'), times: 9); // next cycle

        $this->assertSame(4, $usage->report()['ok']['amazon']);
        $this->assertSame(3, $usage->report($usage->cycleFor('2026-10'))['ok']['amazon']);
        $this->assertSame(9, $usage->report($usage->cycleFor('2026-12'))['ok']['amazon']);
        $this->assertArrayHasKey('2026-10', $usage->cycleOptions());

        // Previous cycles are complete: the projection is the actual total.
        $prev = $usage->report($usage->cycleFor('2026-10'));
        $this->assertTrue($prev['over']);
        $this->assertEqualsWithDelta(3.0, $prev['projected_units'], 1e-9);
    }

    public function test_alert_fires_once_per_threshold_per_cycle(): void
    {
        Limits::set('data.capacity_amazon_calls', 100);
        Limits::set('data.capacity_flipkart_calls', 50); // 1 Flipkart call = 2 units
        $alerts = Mockery::mock(Alerts::class);
        $alerts->shouldReceive('send')->once()->with(Mockery::on(fn ($m) => str_contains($m, '80.0%') && ! preg_match('/oxylabs|omkar|openwebninja/i', $m)), 'warning');
        $alerts->shouldReceive('send')->once()->with(Mockery::pattern('/used up/'), 'error');
        $this->app->instance(Alerts::class, $alerts);
        $usage = app(DataUsage::class);

        $this->logCall('amazon', true, times: 70);
        $this->assertNull($usage->checkAlerts());
        $this->logCall('flipkart', true, times: 5); // 70 + 10 = 80 units
        $this->logCall('amazon', false, times: 500); // failed calls never push usage up
        $this->assertSame(80, $usage->checkAlerts());
        $this->assertNull($usage->checkAlerts());
        $this->assertNull($usage->checkAlerts());

        $this->logCall('amazon', true, times: 20);
        $this->assertSame(100, $usage->checkAlerts());
        $this->assertNull($usage->checkAlerts());
        $this->assertSame(100, $usage->alertedLevel($usage->cycle()['key']));
    }

    public function test_new_cycle_alerts_again(): void
    {
        Limits::set('data.capacity_amazon_calls', 10);
        $alerts = Mockery::mock(Alerts::class);
        $alerts->shouldReceive('send')->twice();
        $this->app->instance(Alerts::class, $alerts);
        $usage = app(DataUsage::class);

        Carbon::setTestNow(Carbon::parse('2026-10-20 12:00', 'Asia/Dhaka'));
        $this->logCall('amazon', true, times: 9);
        $this->assertSame(80, $usage->checkAlerts());

        Carbon::setTestNow(Carbon::parse('2026-11-20 12:00', 'Asia/Dhaka'));
        $this->assertNull($usage->checkAlerts()); // last cycle's calls do not count
        $this->logCall('amazon', true, times: 8);
        $this->assertSame(80, $usage->checkAlerts());
    }

    public function test_page_is_gated_by_permission(): void
    {
        foreach (['super_admin', 'manager', 'finance'] as $role) {
            $this->as($this->staff($role));
            $this->get(DataUsagePage::getUrl())->assertOk();
        }
        // Legacy "admin" staff (no role pivot) map to Super Admin.
        $this->as($this->staff(null, 'admin'));
        $this->get(DataUsagePage::getUrl())->assertOk();

        foreach (['support', 'catalog', 'marketing', 'order_ops'] as $role) {
            $this->as($this->staff($role));
            $this->get(DataUsagePage::getUrl())->assertForbidden();
            $this->assertFalse(DataUsagePage::canAccess());
        }
    }

    public function test_page_renders_stats_banner_and_no_provider_names(): void
    {
        Limits::set('data.capacity_amazon_calls', 1000);
        Limits::set('data.capacity_flipkart_calls', 500);
        $this->logCall('amazon', true, times: 700);
        $this->logCall('flipkart', true, times: 60);
        $this->logCall('flipkart', false, purpose: 'search', times: 4);
        $this->logCall('amazon', true, purpose: 'variant', retry: true, times: 2);

        $this->as($this->staff('finance'));
        $html = Livewire::test(DataUsagePage::class)
            ->assertOk()
            ->assertSee('Product data usage')
            ->assertSee('82.2%')                                 // (702 + 60 × 2) / 1000
            ->assertSee('Usage has passed 80% of the monthly plan')
            ->assertSee('or ≈ 89 Flipkart calls')
            ->assertSee('≈ 178')                                 // remaining Amazon calls
            ->assertSee('Flipkart calls')
            ->assertSee('Product variants (sizes, colours)')
            ->html();
        $this->assertDoesNotMatchRegularExpression('/oxylabs|omkar|openwebninja|rapidapi/i', $html);
    }

    public function test_csv_export(): void
    {
        $at = now()->setTimezone('Asia/Dhaka')->startOfDay()->addHours(10);
        $this->logCall('amazon', true, purpose: 'search', at: $at, times: 3);
        $this->logCall('amazon', false, purpose: 'search', at: $at, times: 2);
        $this->logCall('flipkart', true, purpose: 'product', retry: true, at: $at, times: 1);

        $usage = app(DataUsage::class);
        [$headers, $rows] = $usage->csv($usage->cycle());
        $this->assertSame('Successful calls (billable)', $headers[3]);
        $this->assertCount(2, $rows);
        $byStore = collect($rows)->keyBy(2);
        $this->assertSame([$at->toDateString(), 'Search results', 'Amazon India', 3, 2, 0, 3.0], $byStore['Amazon India']);
        $this->assertSame(1, $byStore['Flipkart'][5]);
        $this->assertEqualsWithDelta(220000 / 110000, $byStore['Flipkart'][6], 0.01);

        $this->as($this->staff('finance'));
        Livewire::test(DataUsagePage::class)->callAction('csv')->assertFileDownloaded();
        $this->assertDatabaseHas('activity_log', ['action' => 'export']);
    }
}

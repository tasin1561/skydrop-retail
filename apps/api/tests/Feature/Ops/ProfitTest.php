<?php

namespace Tests\Feature\Ops;

use App\Filament\Pages\Accounting;
use App\Filament\Pages\Profit;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\FxPaidRate;
use App\Models\Order;
use App\Services\Finance\AccountingService;
use App\Services\Finance\ProfitService;
use App\Support\Knobs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ProfitTest extends TestCase
{
    use OpsTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['finance.fraud.couriers.steadfast.api_key' => null]);
        Knobs::put('finance.gateway_fees.bkash', 2);
        Knobs::put('finance.skydrop_cost.base_bdt', 100);
        Knobs::put('finance.skydrop_cost.per_kg_bdt', 300);
        Knobs::put('finance.usd_bdt', 100);
    }

    public function test_profit_for_one_order(): void
    {
        $order = $this->makeOrder(['fx_paid' => 1.6], ['actual_paid_inr' => 900]);
        $this->pay($order, 'bkash', 2600, 'TRX1');
        DB::table('api_logs')->insert(['provider' => 'oxylabs', 'type' => 'product', 'status' => 200, 'ms' => 1, 'cost_usd' => 0.5, 'created_at' => now()]);

        $p = app(ProfitService::class)->forOrder($order->refresh());

        $this->assertSame(2600.0, $p['revenue']);
        $this->assertEquals(1440, $p['cogs']);              // ₹900 × 1.6
        $this->assertEquals(-90, $p['fx_gain']);            // 900 × (1.5 − 1.6)
        $this->assertEquals(52, $p['gateway_fees']);        // 2% of 2600
        $this->assertEquals(400, $p['shipping_cost']);      // 100 + 300 × 1 kg
        $this->assertEquals(200, $p['shipping_profit']);    // charged 600
        $this->assertEquals(50, $p['api_cost']);            // $0.50 × 100 / 1 order
        $this->assertEquals(2600 - 1440 - 52 - 400 - 50, $p['profit']);
        $this->assertFalse($p['estimated']);
    }

    public function test_estimated_cost_daily_fx_refunds_and_points(): void
    {
        FxPaidRate::query()->create(['date' => now()->subDays(3)->toDateString(), 'rate' => 1.55]);
        $order = $this->makeOrder(['discount_bdt' => 300, 'points_discount_bdt' => 100, 'total_bdt' => 2300]);
        $pay = $this->pay($order, 'bkash', 2300, 'TRX2');
        $pay->forceFill(['refunded_bdt' => 200])->save();

        $p = app(ProfitService::class)->forOrder($order->refresh());

        $this->assertTrue($p['estimated']);
        $this->assertEquals(1.55, $p['fx_paid']);
        $this->assertEquals(1550, $p['cogs']);              // unit_inr 1000 × 1.55
        $this->assertEquals(100, $p['points_bdt']);
        $this->assertEquals(200, $p['discounts_bdt']);
        $this->assertEquals(200, $p['refunds']);
        $this->assertEquals(2300 - 1550 - 46 - 400 - 200, $p['profit']);
    }

    public function test_report_aggregates_by_store_category_and_day_and_pages_render(): void
    {
        $this->makeOrder();
        $this->makeOrder(['status' => 'cancelled']);
        $this->makeOrder([], ['product_id' => 'B0OPS00002', 'market' => 'flipkart', 'category' => 'fashion']);

        $r = app(ProfitService::class)->report(Carbon::now()->subDay(), Carbon::now());
        $this->assertSame(2, $r['kpis']['orders']);
        $this->assertEqualsCanonicalizing(['amazon', 'flipkart'], array_keys($r['by_store']));
        $this->assertArrayHasKey('fashion', $r['by_category']);
        $this->assertCount(1, $r['by_day']);

        $filtered = app(ProfitService::class)->report(Carbon::now()->subDay(), Carbon::now(), ['store' => 'flipkart']);
        $this->assertSame(1, $filtered['kpis']['orders']);

        $admin = $this->staff();
        $this->actingAs($admin);
        $this->get(Profit::getUrl())->assertOk()->assertSee('Worst-margin orders');
        $this->get(Accounting::getUrl())->assertOk()->assertSee('Month close');
        Livewire::test(Profit::class)->callAction('csv')->assertFileDownloaded();
        $order = Order::query()->where('status', 'delivered')->first();
        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertSee('Profit');
    }

    public function test_accounting_journal_liabilities_and_month_close(): void
    {
        $o = $this->makeOrder();
        $this->pay($o, 'bkash', 2600, 'TRX9');
        $svc = app(AccountingService::class);
        [$h, $rows] = $svc->salesJournal(now()->subDay(), now());
        $this->assertSame('net_sales_bdt', $h[8]);
        $this->assertEquals(2600, $rows[0][8]);
        [, $pay] = $svc->payments(now()->subDay(), now());
        $this->assertSame('TRX9', $pay[0][4]);
        $this->assertArrayHasKey('points', $svc->liabilities());
        $close = $svc->monthClose(now()->format('Y-m'));
        $this->assertSame(1, $close['kpis']['orders']);
    }
}

<?php

namespace Tests\Feature\Ops;

use App\Filament\Pages\FinanceSecuritySettings;
use App\Models\Setting;
use App\Services\Fraud\Couriers\CarryBeeDriver;
use App\Services\Fraud\Couriers\PathaoDriver;
use App\Services\Fraud\FraudService;
use App\Support\Knobs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

/** Pathao + CarryBee courier history for the fraud check, credentials from admin settings. */
class CourierDriversTest extends TestCase
{
    use OpsTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'finance.fraud.couriers.steadfast.enabled' => false,
            'finance.fraud.couriers.pathao.username' => 'ops@skydrop.test',
            'finance.fraud.couriers.pathao.password' => 'pathao-pass',
            'finance.fraud.couriers.carrybee.phone' => '01755000000',
            'finance.fraud.couriers.carrybee.password' => 'cb-pass',
        ]);
        Knobs::forget();
    }

    private function fakePathao(int $total, int $ok, int $firstLookupStatus = 200): void
    {
        $lookups = 0;
        Http::fake([
            'merchant.pathao.com/api/v1/login' => Http::sequence()
                ->push(['access_token' => 'tok-1'])->push(['access_token' => 'tok-2']),
            'merchant.pathao.com/api/v1/user/success' => function () use (&$lookups, $total, $ok, $firstLookupStatus) {
                return ++$lookups === 1 && $firstLookupStatus !== 200
                    ? Http::response(['message' => 'Unauthenticated'], $firstLookupStatus)
                    : Http::response(['data' => ['customer' => ['total_delivery' => $total, 'successful_delivery' => $ok]]]);
            },
            'merchant.carrybee.com/*' => Http::response([], 500),
        ]);
    }

    private function fakeCarryBee(int $total, int $cancelled): void
    {
        Http::fake([
            'merchant.pathao.com/*' => Http::response([], 500),
            'merchant.carrybee.com/api/auth/csrf' => Http::response(['csrfToken' => 'csrf-1']),
            'merchant.carrybee.com/api/auth/callback/login*' => Http::response(['url' => 'https://merchant.carrybee.com/']),
            'merchant.carrybee.com/api/auth/session' => Http::response(['accessToken' => 'cb-tok', 'user' => ['selectedBusinessId' => 15834]]),
            'api-merchant.carrybee.com/*' => Http::response(['error' => false, 'data' => ['total_order' => $total, 'cancelled_order' => $cancelled, 'success_rate' => 0]]),
        ]);
    }

    public function test_pathao_signs_in_checks_the_phone_and_reuses_the_token(): void
    {
        $this->fakePathao(12, 3);
        $d = app(PathaoDriver::class);
        $this->assertTrue($d->enabled());

        $r = $d->check('+8801711000000');
        $this->assertSame(['total' => 12, 'delivered' => 3, 'cancelled' => 9], array_slice($r, 0, 3));
        $d->check('01811000000');

        $logins = Http::recorded(fn (Request $q) => str_ends_with($q->url(), '/api/v1/login'));
        $this->assertCount(1, $logins, 'token is cached between checks');
        $this->assertSame(['username' => 'ops@skydrop.test', 'password' => 'pathao-pass'], $logins[0][0]->data());
        Http::assertSent(fn (Request $q) => str_ends_with($q->url(), '/user/success') && $q['phone'] === '01711000000'
            && $q->hasHeader('Authorization', 'Bearer tok-1'));
    }

    public function test_pathao_refreshes_an_expired_token_once(): void
    {
        $this->fakePathao(4, 4, 401);
        $r = app(PathaoDriver::class)->check('01711000000');
        $this->assertSame(4, $r['delivered']);
        Http::assertSent(fn (Request $q) => str_ends_with($q->url(), '/user/success') && $q->hasHeader('Authorization', 'Bearer tok-2'));
    }

    public function test_carrybee_signs_in_like_the_panel_and_checks_the_phone(): void
    {
        $this->fakeCarryBee(10, 2);
        $d = app(CarryBeeDriver::class);
        $this->assertTrue($d->enabled());

        $r = $d->check('8801711000000');
        $this->assertSame(['total' => 10, 'delivered' => 8, 'cancelled' => 2], array_slice($r, 0, 3));

        Http::assertSent(fn (Request $q) => str_contains($q->url(), '/api/auth/callback/login') && $q['phone'] === '+8801755000000'
            && $q['password'] === 'cb-pass' && $q['csrfToken'] === 'csrf-1');
        Http::assertSent(fn (Request $q) => $q->url() === 'https://api-merchant.carrybee.com/api/v2/businesses/15834/fraud-check/01711000000'
            && $q->hasHeader('Authorization', 'Bearer cb-tok'));
    }

    public function test_all_three_couriers_add_up_in_the_score(): void
    {
        config(['finance.fraud.couriers.steadfast.enabled' => true, 'finance.fraud.couriers.steadfast.api_key' => 'k',
            'finance.fraud.couriers.steadfast.secret_key' => 's']);
        Knobs::forget();
        Http::fake([
            'portal.packzy.com/*' => Http::response(['total_parcels' => 2, 'total_delivered' => 0, 'total_cancelled' => 2]),
            'merchant.pathao.com/api/v1/login' => Http::response(['access_token' => 't']),
            'merchant.pathao.com/api/v1/user/success' => Http::response(['data' => ['customer' => ['total_delivery' => 3, 'successful_delivery' => 0]]]),
            'merchant.carrybee.com/api/auth/csrf' => Http::response(['csrfToken' => 'c']),
            'merchant.carrybee.com/api/auth/callback/login*' => Http::response([]),
            'merchant.carrybee.com/api/auth/session' => Http::response(['accessToken' => 'a', 'user' => ['selectedBusinessId' => 1]]),
            'api-merchant.carrybee.com/*' => Http::response(['data' => ['total_order' => 5, 'cancelled_order' => 5]]),
        ]);

        $r = app(FraudService::class)->score('01799000000');
        $this->assertSame(['steadfast', 'pathao', 'carrybee'], array_keys($r['couriers']));
        $this->assertSame(10, $r['courier_total']);
        $this->assertSame('medium', $r['level']);
    }

    public function test_couriers_without_a_login_are_skipped(): void
    {
        config(['finance.fraud.couriers.pathao.password' => null, 'finance.fraud.couriers.carrybee.phone' => null]);
        Knobs::forget();
        Http::fake();
        $this->assertFalse(app(PathaoDriver::class)->enabled());
        $this->assertFalse(app(CarryBeeDriver::class)->enabled());
        $this->assertSame([], app(FraudService::class)->score('01799000000')['couriers']);
        Http::assertNothingSent();
    }

    public function test_admin_saves_courier_logins_encrypted_and_write_only(): void
    {
        config(['finance.fraud.couriers.pathao.password' => null]);
        Knobs::forget();
        $this->actingAs($this->staff());
        $this->get(FinanceSecuritySettings::getUrl())->assertOk()->assertSee('Courier history (fraud check)')->assertDontSee('cb-pass');

        Livewire::test(FinanceSecuritySettings::class)
            ->set('data.finance__fraud__couriers__pathao__username', 'shop@skydrop.test')
            ->set('data.finance__fraud__couriers__pathao__password', 'new-pathao-pass')
            ->call('save')->assertHasNoErrors();
        Knobs::forget();
        $this->assertSame('shop@skydrop.test', Knobs::get('finance.fraud.couriers.pathao.username'));
        $this->assertSame('new-pathao-pass', Setting::get('finance.fraud.couriers.pathao.password'));
        $raw = DB::table('settings')->where('key', 'finance.fraud.couriers.pathao.password')->value('value');
        $this->assertStringNotContainsString('new-pathao-pass', (string) $raw, 'stored encrypted');

        // Saving again with the password left blank keeps it.
        Livewire::test(FinanceSecuritySettings::class)->assertSet('data.finance__fraud__couriers__pathao__password', null)->call('save');
        Knobs::forget();
        $this->assertSame('new-pathao-pass', Setting::get('finance.fraud.couriers.pathao.password'));
    }

    public function test_admin_test_logins_button_reports_each_courier(): void
    {
        $this->fakePathao(0, 0);
        $this->actingAs($this->staff());
        Livewire::test(FinanceSecuritySettings::class)->call('testCouriers')->assertNotified();
    }
}

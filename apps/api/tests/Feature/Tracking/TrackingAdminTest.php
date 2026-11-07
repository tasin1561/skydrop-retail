<?php

namespace Tests\Feature\Tracking;

use App\Filament\Pages\Settings;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Filament\Resources\TrackingEvents\Pages\ListTrackingEvents;
use App\Filament\Resources\TrackingEvents\Pages\ViewTrackingEvent;
use App\Filament\Widgets\SalesBySourceChart;
use App\Models\Setting;
use App\Models\TrackingEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class TrackingAdminTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    private function admin(): User
    {
        $u = new User;
        $u->forceFill(['name' => 'Admin', 'email' => 'admin-t@skydrop.retail', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'admin'])->save();

        return $u;
    }

    public function test_tracking_admin_pages_render(): void
    {
        $shopper = $this->shopper();
        $order = $this->placeOrder($shopper, [$this->product('A1', 1000)], ['attribution' => [
            'first' => ['utm_source' => 'facebook', 'utm_campaign' => 'eid-sale', 'fbclid' => 'IwAR1', 'landing' => '/p/A1'],
            'consent' => 'all',
        ]]);
        $row = TrackingEvent::query()->create(['event' => 'Purchase', 'event_id' => 'purchase_'.$order->id, 'order_id' => $order->id,
            'destination' => 'meta', 'status' => 'failed', 'response_code' => 400, 'error' => 'bad token', 'payload' => ['event' => 'Purchase']]);

        $this->actingAs($this->admin());

        Livewire::test(ListTrackingEvents::class)->assertOk()->assertCanSeeTableRecords([$row])
            ->filterTable('status', ['sent'])->assertCanNotSeeTableRecords([$row]);
        Livewire::test(ViewTrackingEvent::class, ['record' => $row->id])->assertOk()->assertSee('bad token');
        Livewire::test(ViewOrder::class, ['record' => $order->id])->assertOk()->assertSee('Attribution')->assertSee('eid-sale');
        Livewire::test(SalesBySourceChart::class)->assertOk();
        $this->assertSame(['facebook / eid-sale'], array_keys(SalesBySourceChart::rows()));

        Livewire::test(Settings::class)
            ->set('data.tracking.driver', 'both')
            ->set('data.tracking.purchase_on', 'delivered')
            ->set('data.tracking.meta.pixel_id', '12345')
            ->call('save')
            ->assertHasNoErrors();
        $this->assertSame('both', Setting::get('tracking.driver'));
        $this->assertSame('12345', Setting::get('tracking.meta.pixel_id'));
    }
}

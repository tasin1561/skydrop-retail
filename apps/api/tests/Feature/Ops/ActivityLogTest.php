<?php

namespace Tests\Feature\Ops;

use App\Filament\Pages\Settings;
use App\Filament\Resources\ActivityLogs\ActivityLogResource;
use App\Filament\Resources\Orders\Pages\ViewOrder;
use App\Models\ActivityLog;
use App\Models\Coupon;
use App\Models\Setting;
use App\Services\Audit\ActivityLogger;
use Illuminate\Auth\Events\Failed;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityLogTest extends TestCase
{
    use OpsTestHelpers, RefreshDatabase;

    public function test_staff_model_changes_are_logged_with_a_diff(): void
    {
        $admin = $this->staff();
        $this->actingAs($admin);
        $c = Coupon::query()->create(['code' => 'OPS10', 'type' => 'percent_items', 'value' => 10]);
        $c->update(['value' => 15]);

        $log = ActivityLog::query()->where('action', 'updated')->where('subject_type', Coupon::class)->first();
        $this->assertNotNull($log);
        $this->assertSame($admin->id, $log->user_id);
        $this->assertEquals([10, 15], array_map('floatval', $log->changes['value']));
        $this->assertTrue(ActivityLog::query()->where('action', 'created')->where('subject_id', (string) $c->id)->exists());
    }

    public function test_shopper_and_system_changes_are_not_logged(): void
    {
        Coupon::query()->create(['code' => 'SYS', 'type' => 'fixed', 'value' => 50]);
        $this->assertSame(0, ActivityLog::query()->where('subject_type', Coupon::class)->count());
    }

    public function test_filament_actions_logins_and_secrets(): void
    {
        $admin = $this->staff();
        $this->actingAs($admin);
        $order = $this->makeOrder(['status' => 'placed']);

        Livewire::test(ViewOrder::class, ['record' => $order->id])
            ->callAction('notes', ['staff_notes' => 'Called the customer']);
        $this->assertTrue(ActivityLog::query()->where('action', 'action:notes')->where('subject_id', $order->id)->exists());

        event(new Failed('web', null, ['email' => 'intruder@x.test', 'password' => 'guess']));
        $failed = ActivityLog::query()->where('action', 'login_failed')->first();
        $this->assertNotNull($failed);
        $this->assertStringNotContainsString('guess', json_encode($failed->meta));

        Setting::put('tracking.meta.access_token', 'EAAB-super-secret-token-1234');
        $log = ActivityLog::query()->where('subject_type', Setting::class)->latest('id')->first();
        $this->assertStringNotContainsString('super-secret', json_encode($log->changes));

        $this->get(ActivityLogResource::getUrl())->assertOk()->assertSee('action:notes');
    }

    public function test_logger_redacts_and_never_throws(): void
    {
        $log = ActivityLogger::log('export', null, ['password' => 'x', 'nested' => ['api_key' => 'y', 'ok' => 1]], 'test');
        $this->assertSame('[redacted]', $log->changes['password']);
        $this->assertSame('[redacted]', $log->changes['nested']['api_key']);
        $this->assertSame(1, $log->changes['nested']['ok']);
    }

    public function test_secret_settings_are_encrypted_at_rest_and_plaintext_still_reads(): void
    {
        Setting::put('tracking.meta.access_token', 'EAAB-token-abcd');
        $raw = DB::table('settings')->where('key', 'tracking.meta.access_token')->value('value');
        $this->assertStringStartsWith('enc:', $raw);
        $this->assertStringNotContainsString('EAAB', $raw);
        $this->assertSame('EAAB-token-abcd', Setting::get('tracking.meta.access_token'));

        // Older plaintext JSON rows still read; non-secret keys stay plain.
        DB::table('settings')->insert(['key' => 'tracking.delivro.key', 'value' => json_encode('old-plain-key')]);
        $this->assertSame('old-plain-key', Setting::get('tracking.delivro.key'));
        Setting::put('pricing.fx_buffer', 0.02);
        $this->assertSame('0.02', DB::table('settings')->where('key', 'pricing.fx_buffer')->value('value'));

        $this->assertTrue(Setting::isSecret('tracking.ga4.api_secret'));
        $this->assertTrue(Setting::isSecret('tracking.feed_key'));
        $this->assertFalse(Setting::isSecret('tracking.meta.pixel_id'));
        $this->assertSame('••••abcd', Setting::mask('EAAB-token-abcd'));
    }

    public function test_settings_page_masks_secrets_and_blank_keeps_them(): void
    {
        $this->actingAs($this->staff());
        Setting::put('tracking.meta.access_token', 'EAAB-token-wxyz');
        Livewire::test(Settings::class)
            ->assertSet('data.tracking.meta.access_token', '••••wxyz')
            ->call('save');
        $this->assertSame('EAAB-token-wxyz', Setting::get('tracking.meta.access_token'));
    }
}

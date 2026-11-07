<?php

namespace Tests\Feature\Messaging;

use App\Filament\Pages\Broadcast;
use App\Filament\Pages\MessagingSettings;
use App\Filament\Resources\MessageTemplates\Pages\EditMessageTemplate;
use App\Filament\Resources\MessageTemplates\Pages\ListMessageTemplates;
use App\Filament\Resources\NotificationLogs\Pages\ListNotificationLogs;
use App\Models\MessageTemplate;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Commerce\Sms\LogSms;
use App\Services\Messaging\MessagingSettings as Store;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MessagingAdminTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['retail.sms.driver' => 'log']);
        LogSms::$sent = [];
        $admin = new User;
        $admin->forceFill(['name' => 'Ops', 'email' => 'ops@skydrop.retail', 'phone' => '+8801700000001', 'password' => 'secret-password', 'is_admin' => true, 'role' => 'admin'])->save();
        $this->actingAs($admin);
        $this->seed(MessageTemplateSeeder::class);
    }

    public function test_templates_list_edit_preview_and_test_send(): void
    {
        Livewire::test(ListMessageTemplates::class)->assertOk()->assertCanSeeTableRecords(MessageTemplate::query()->where('key', 'back_in_stock')->get());
        $sms = MessageTemplate::query()->where(['key' => 'order_placed', 'channel' => 'sms', 'locale' => 'bn'])->first();
        Livewire::test(EditMessageTemplate::class, ['record' => $sms->id])->assertOk()
            ->assertSee('UCS-2')
            ->fillForm(['body' => 'Skydrop: {{order_id}} OK'])->call('save')->assertHasNoFormErrors()
            ->callAction('testSend', ['to' => '01711111111']);
        $this->assertSame('Skydrop: SR-24117-AB12 OK', collect(LogSms::$sent)->last()['message']);
        $this->assertSame('sent', NotificationLog::query()->where('category', 'test')->value('status'));

        $email = MessageTemplate::query()->where(['key' => 'order_placed', 'channel' => 'email', 'locale' => 'en'])->first();
        Livewire::test(EditMessageTemplate::class, ['record' => $email->id])->assertOk()->assertSee('srcdoc', false);
    }

    public function test_log_lists_masks_and_resends(): void
    {
        $log = NotificationLog::query()->create(['event' => 'order_placed', 'channel' => 'sms', 'to' => '+8801712345678', 'body' => 'Hi', 'status' => 'failed', 'error' => 'gateway down']);
        Livewire::test(ListNotificationLogs::class)->assertOk()->assertCanSeeTableRecords([$log])
            ->assertSee('+88017•••••678')->assertDontSee('+8801712345678')
            ->filterTable('status', 'failed')->assertCanSeeTableRecords([$log])
            ->callTableAction('resend', $log);
        $this->assertSame(2, NotificationLog::query()->count());
        $this->assertSame('sent', NotificationLog::query()->latest('id')->value('status'));
    }

    public function test_broadcast_dry_run_and_send(): void
    {
        User::query()->create(['name' => 'A', 'phone' => '+8801711111111']);
        Livewire::test(Broadcast::class)->assertOk()
            ->fillForm(['segment' => 'all', 'channels' => ['sms', 'inapp'], 'subject' => 'Eid', 'body' => 'Eid sale is live'])
            ->call('dryRun')->assertSet('estimate.users', 1)->assertSet('estimate.sms', 1)
            ->call('send');
        $this->assertSame(1, NotificationLog::query()->whereNotNull('broadcast_id')->where('channel', 'sms')->count());
    }

    public function test_messaging_settings_save_keeps_secrets_write_only(): void
    {
        Livewire::test(MessagingSettings::class)->assertOk()
            ->fillForm(['sms' => ['failover' => ['bulksmsbd', 'log'], 'bulksmsbd' => ['api_key' => 'K-1', 'sender_id' => '880961']], 'reminders' => ['payment_after_minutes' => 45]])
            ->call('save')->assertHasNoFormErrors();
        $this->assertSame(['bulksmsbd', 'log'], Store::smsChain());
        $this->assertSame('K-1', Store::get('sms.bulksmsbd.api_key'));
        $this->assertSame(45, Store::get('reminders.payment_after_minutes'));

        Livewire::test(MessagingSettings::class)->assertSet('data.sms.bulksmsbd.api_key', null)->call('save');
        $this->assertSame('K-1', Store::get('sms.bulksmsbd.api_key'));
    }
}

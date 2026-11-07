<?php

namespace Tests\Feature\Messaging;

use App\Jobs\Messaging\SendBroadcast;
use App\Jobs\Messaging\SendCartReminders;
use App\Jobs\Messaging\SendPaymentReminders;
use App\Jobs\Messaging\SendReviewRequests;
use App\Mail\BrandedMessage;
use App\Models\Cart;
use App\Models\NotificationLog;
use App\Models\Payment;
use App\Models\PushSubscription;
use App\Models\User;
use App\Models\UserNotification;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Sms\LogSms;
use App\Services\Messaging\BroadcastAudience;
use App\Services\Messaging\Messenger;
use App\Services\Messaging\Prefs;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class MessagingFlowTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
        config(['messaging.quiet_hours' => ['start' => '00:00', 'end' => '00:00', 'timezone' => 'Asia/Dhaka']]); // off in tests
        Http::fake(['exp.host/*' => Http::response(['data' => [['status' => 'ok', 'id' => 'ticket-1']]])]);
        RateLimiter::clear('sms:phone:+8801712345678');
    }

    public function test_order_placed_sends_sms_branded_email_push_and_in_app(): void
    {
        Mail::fake();
        $u = $this->shopper(['email' => 'rahim@example.com']);
        PushSubscription::register($u->id, 'expo', 'ExponentPushToken[abc]', null, 'android');
        $o = $this->placeOrder($u, [$this->product('A1', 1000), $this->product('F1', 500, 'flipkart')]);

        $logs = NotificationLog::query()->where('order_id', $o->id)->where('event', 'order_placed')->get()->keyBy('channel');
        $this->assertSame(['sms', 'email', 'push'], $logs->keys()->all());
        $this->assertTrue($logs->every(fn ($l) => $l->status === 'sent'));
        $this->assertSame(1, $logs['sms']->segments);
        $this->assertStringContainsString($o->id, collect(LogSms::$sent)->last()['message']);

        Mail::assertSent(BrandedMessage::class, function (BrandedMessage $m) use ($o) {
            $html = $m->render();

            return $m->hasTo('rahim@example.com') && str_contains($html, $o->id) && str_contains($html, 'Mega Store')
                && str_contains($html, 'Style Bazaar') && str_contains($html, 'invoices/') && $m->hasReplyTo(config('messaging.mail.reply_to'));
        });
        Http::assertSent(function ($r) use ($o) {
            $m = json_decode($r->body(), true)[0] ?? [];

            return str_contains($r->url(), 'exp.host') && $m['to'] === 'ExponentPushToken[abc]' && $m['data']['route'] === '/order/'.$o->id;
        });

        $n = UserNotification::query()->where('user_id', $u->id)->where('type', 'order_placed')->first();
        $this->assertSame('/orders/'.$o->id, $n->link);
        $this->assertSame('order', $n->icon);
    }

    public function test_email_only_when_user_has_one_and_bangla_locale(): void
    {
        Mail::fake();
        $u = $this->shopper();
        $u->forceFill(['locale' => 'bn'])->save();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);

        $this->assertFalse(NotificationLog::query()->where('order_id', $o->id)->where('channel', 'email')->exists());
        $sms = NotificationLog::query()->where('order_id', $o->id)->where('channel', 'sms')->first();
        $this->assertSame('bn', $sms->locale);
        $this->assertStringContainsString('গ্রহণ', $sms->body);
        Mail::assertNothingSent();
    }

    public function test_every_status_change_notifies_with_rider_due(): void
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)], ['payment' => 'advance50', 'paymentVia' => 'bkash']);
        $wf = app(OrderWorkflow::class);
        foreach (['verified', 'purchased', 'india_hub', 'in_transit', 'dhaka_hub', 'out_for_delivery'] as $s) {
            $wf->transition($o->refresh(), $s, 'admin');
        }
        $events = UserNotification::query()->where('user_id', $u->id)->pluck('type')->all();
        foreach (['order_verified', 'order_purchased', 'order_india_hub', 'order_in_transit', 'order_dhaka_hub', 'order_out_for_delivery'] as $e) {
            $this->assertContains($e, $events);
        }
        // SMS for minor steps is off by default; out for delivery tells the rider's due amount.
        $this->assertFalse(NotificationLog::query()->where('event', 'order_in_transit')->where('channel', 'sms')->exists());
        $sms = NotificationLog::query()->where('event', 'order_out_for_delivery')->where('channel', 'sms')->value('body');
        $this->assertMatchesRegularExpression('/keep BDT [\d,]+ ready/', $sms);
    }

    public function test_marketing_opt_out_respected_but_transactional_always_sent(): void
    {
        $u = $this->shopper(['email' => 'a@example.com']);
        Prefs::set($u, ['sms' => false, 'inapp' => false]);

        app(Messenger::class)->raw($u->refresh(), ['sms', 'email', 'inapp'], 'Eid sale', 'Up to 20% off', 'marketing');
        $this->assertFalse(NotificationLog::query()->where('event', 'broadcast')->where('channel', 'sms')->exists());
        $this->assertTrue(NotificationLog::query()->where('event', 'broadcast')->where('channel', 'email')->exists());
        $this->assertSame(0, UserNotification::query()->where('type', 'broadcast')->count());
        $this->assertNotNull(NotificationLog::query()->where('event', 'broadcast')->where('channel', 'email')->value('meta')['unsubscribe'] ?? null);

        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $this->assertTrue(NotificationLog::query()->where('order_id', $o->id)->where('channel', 'sms')->exists());
        $this->assertTrue(UserNotification::query()->where('type', 'order_placed')->exists());
    }

    public function test_feature_switches_gate_channels_and_marketing(): void
    {
        $u = $this->shopper();
        Features::set('sms', false);
        Features::set('in_app_notifications', false);
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $this->assertFalse(NotificationLog::query()->where('order_id', $o->id)->where('channel', 'sms')->exists());
        $this->assertSame(0, UserNotification::query()->count());
        $this->getJson('/api/v1/notifications')->assertNotFound()->assertJsonPath('feature', 'in_app_notifications');

        Features::set('marketing_messages', false);
        Features::set('in_app_notifications', true);
        $this->assertSame([], app(Messenger::class)->raw($u, ['email', 'push', 'inapp'], 'Sale', 'x', 'marketing'));
        $this->assertSame(0, UserNotification::query()->count());
    }

    public function test_unsubscribe_link_and_quiet_hours(): void
    {
        $u = $this->shopper();
        $url = URL::signedRoute('messaging.unsubscribe', ['user' => $u->id, 'channel' => 'email']);
        $this->get($url)->assertOk()->assertSee('unsubscribed');
        $this->assertFalse(Prefs::of($u->refresh())['marketing']['email']);
        $this->get(str_replace('signature=', 'signature=x', $url))->assertForbidden();

        config(['messaging.quiet_hours' => ['start' => '22:00', 'end' => '09:00', 'timezone' => 'Asia/Dhaka']]);
        $this->travelTo(Carbon::parse('2026-11-07 23:30', 'Asia/Dhaka'));
        $this->assertSame('2026-11-08 09:00', Messenger::quietUntil()->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'));
        $this->travelTo(Carbon::parse('2026-11-08 08:00', 'Asia/Dhaka'));
        $this->assertSame('2026-11-08 09:00', Messenger::quietUntil()->setTimezone('Asia/Dhaka')->format('Y-m-d H:i'));
        $this->travelTo(Carbon::parse('2026-11-08 12:00', 'Asia/Dhaka'));
        $this->assertNull(Messenger::quietUntil());
    }

    public function test_abandoned_cart_flow(): void
    {
        $u = $this->shopper(['email' => 'a@example.com']);
        PushSubscription::register($u->id, 'expo', 'ExponentPushToken[abc]');
        $p = $this->product('A1', 1000);
        $items = [['productId' => 'A1', 'market' => 'amazon', 'title' => 'Earbuds', 'unitBdt' => 2000, 'qty' => 2]];
        $this->putJson('/api/v1/cart', ['items' => $items])->assertOk();
        $this->getJson('/api/v1/cart')->assertOk()->assertJsonPath('items.0.productId', 'A1');
        $this->assertSame(4000, Cart::query()->value('total_bdt'));

        $this->travel(30)->minutes();
        dispatch_sync(new SendCartReminders);
        $this->assertSame(0, NotificationLog::query()->where('event', 'like', 'cart_%')->count());

        $this->travel(31)->minutes();
        dispatch_sync(new SendCartReminders);
        $this->assertSame(['push'], NotificationLog::query()->where('event', 'cart_reminder_1h')->pluck('channel')->all());
        dispatch_sync(new SendCartReminders); // idempotent
        $this->assertSame(1, NotificationLog::query()->where('event', 'cart_reminder_1h')->count());

        $this->travel(23)->hours();
        dispatch_sync(new SendCartReminders);
        $this->assertEqualsCanonicalizing(['sms', 'email'], NotificationLog::query()->where('event', 'cart_reminder_24h')->pluck('channel')->all());

        // A new cart, then an order: no more reminders.
        $this->putJson('/api/v1/cart', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => 2000]]])->assertOk();
        $this->assertNull(Cart::query()->value('reminded_1h_at'));
        $this->placeOrder($u, [$p]);
        $this->assertNotNull(Cart::query()->value('converted_at'));
        $this->travel(2)->days();
        dispatch_sync(new SendCartReminders);
        $this->assertSame(1, NotificationLog::query()->where('event', 'cart_reminder_1h')->count());

        $this->putJson('/api/v1/cart', ['items' => []])->assertOk();
        $this->assertSame(0, Cart::query()->count());
    }

    public function test_abandoned_cart_respects_marketing_opt_out(): void
    {
        $u = $this->shopper();
        Prefs::set($u, ['sms' => false, 'push' => false]);
        $this->putJson('/api/v1/cart', ['items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => 900]]])->assertOk();
        $this->travel(25)->hours();
        dispatch_sync(new SendCartReminders);
        $this->assertSame(0, NotificationLog::query()->count());
        $this->assertNotNull(Cart::query()->value('reminded_24h_at'));
    }

    public function test_payment_reminder_and_review_request(): void
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        dispatch_sync(new SendPaymentReminders);
        $this->assertFalse(NotificationLog::query()->where('event', 'payment_pending')->exists());
        $this->travel(31)->minutes();
        dispatch_sync(new SendPaymentReminders);
        dispatch_sync(new SendPaymentReminders);
        $this->assertSame(1, NotificationLog::query()->where('event', 'payment_pending')->where('channel', 'sms')->count());
        $this->assertStringContainsString('?pay=1', NotificationLog::query()->where('event', 'payment_pending')->value('body'));

        app(OrderWorkflow::class)->transition($o->refresh(), 'delivered', 'admin');
        $this->travel(73)->hours();
        dispatch_sync(new SendReviewRequests);
        $this->assertTrue(UserNotification::query()->where('type', 'review_request')->exists());
    }

    public function test_failed_payment_notifies_with_retry_link(): void
    {
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $payment = Payment::query()->where('order_id', $o->id)->first()
            ?? Payment::query()->create(['order_id' => $o->id, 'gateway' => 'mock', 'amount_bdt' => $o->total_bdt, 'status' => 'pending', 'kind' => 'full']);
        $payment->forceFill(['status' => 'failed'])->save();
        $body = NotificationLog::query()->where('event', 'payment_failed')->where('channel', 'sms')->value('body');
        $this->assertStringContainsString('/orders/'.$o->id.'?pay=1', $body);
    }

    public function test_broadcast_chunks_and_respects_opt_outs(): void
    {
        $a = User::query()->create(['name' => 'A', 'phone' => '+8801711111111']);
        $b = User::query()->create(['name' => 'B', 'phone' => '+8801722222222']);
        Prefs::set($b, ['sms' => false]);
        User::query()->create(['name' => 'Staff', 'phone' => '+8801733333333'])->forceFill(['is_admin' => true])->save();

        $est = BroadcastAudience::estimate(['type' => 'all'], ['sms', 'inapp'], 'Eid sale!');
        $this->assertSame(2, $est['users']);
        $this->assertSame(1, $est['sms']);
        $this->assertSame(2, $est['inapp']);

        dispatch_sync(new SendBroadcast('bc_test', ['type' => 'all'], ['sms', 'inapp'], 'Eid', 'Eid sale!'));
        $this->assertSame([$a->id], NotificationLog::query()->where('broadcast_id', 'bc_test')->pluck('user_id')->all());
        $this->assertSame(2, UserNotification::query()->where('type', 'broadcast')->count());
    }

    public function test_sms_rate_limit_per_phone(): void
    {
        config(['messaging.sms.per_phone_per_hour' => 2]);
        $u = $this->shopper();
        foreach (range(1, 3) as $i) {
            app(Messenger::class)->raw($u, ['sms'], 'x', "Message {$i}");
        }
        $this->assertSame(['sent', 'sent', 'failed'], NotificationLog::query()->orderBy('id')->pluck('status')->all());
    }

    public function test_otp_still_works_and_code_is_not_logged(): void
    {
        RateLimiter::clear('otp:phone:+8801712345678');
        RateLimiter::clear('otp:ip:127.0.0.1');
        Features::set('sms', false); // OTP ignores the SMS switch
        $code = $this->postJson('/api/v1/auth/otp/request', ['phone' => '01712345678'])->assertOk()->json('demoCode');
        $this->assertStringContainsString($code, collect(LogSms::$sent)->last()['message']);
        $log = NotificationLog::query()->where('event', 'otp')->first();
        $this->assertSame('otp', $log->category);
        $this->assertSame('sent', $log->status);
        $this->assertStringNotContainsString($code, $log->body);

        $this->postJson('/api/v1/auth/otp/verify', ['phone' => '01712345678', 'code' => $code, 'name' => 'Rahim'])->assertOk()->assertJsonPath('isNew', true);
        $this->assertTrue(UserNotification::query()->where('type', 'welcome')->exists());
    }
}

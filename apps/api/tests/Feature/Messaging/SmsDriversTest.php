<?php

namespace Tests\Feature\Messaging;

use App\Models\Setting;
use App\Services\Commerce\Sms\BulkSmsBd;
use App\Services\Commerce\Sms\SmsManager;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class SmsDriversTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'messaging.sms.bulksmsbd' => ['url' => 'https://bulksmsbd.test/api', 'api_key' => 'KEY', 'sender_id' => '8809617000000', 'timeout' => 5],
            'messaging.sms.sslwireless' => ['url' => 'https://ssl.test/api/v3', 'api_token' => 'TOK', 'sid' => 'SKYDROP', 'timeout' => 5],
        ]);
    }

    public function test_bulksmsbd_sends_with_normalised_number_and_reports_message_id(): void
    {
        Http::fake(['bulksmsbd.test/api/smsapi' => Http::response(['response_code' => 202, 'message_id' => 9911, 'success_message' => 'SMS Submitted Successfully'])]);

        $r = app(BulkSmsBd::class)->deliver('+8801712345678', 'Hello');
        $this->assertTrue($r->ok);
        $this->assertSame('9911', $r->messageId);
        Http::assertSent(fn (Request $req) => $req['number'] === '8801712345678' && $req['api_key'] === 'KEY'
            && $req['senderid'] === '8809617000000' && $req['type'] === 'text' && $req['message'] === 'Hello');
    }

    public function test_bulksmsbd_unicode_and_error_codes_and_balance(): void
    {
        Http::fake([
            'bulksmsbd.test/api/smsapi' => Http::sequence()
                ->push(['response_code' => 202, 'message_id' => 1])
                ->push(['response_code' => 1007, 'error_message' => 'Insufficient balance'])
                ->push(['response_code' => 1001]),
            'bulksmsbd.test/api/getBalanceApi*' => Http::response(['response_code' => 202, 'balance' => 153.25]),
        ]);
        $d = app(BulkSmsBd::class);

        $this->assertTrue($d->deliver('+8801712345678', 'আপনার কোড ১২৩')->ok);
        Http::assertSent(fn (Request $req) => str_contains($req->url(), 'smsapi') && $req['type'] === 'unicode');

        $low = $d->deliver('+8801712345678', 'Hi');
        $this->assertFalse($low->ok);
        $this->assertTrue($low->retryable);
        $this->assertStringContainsString('1007', $low->error);

        $bad = $d->deliver('+8801712345678', 'Hi');
        $this->assertFalse($bad->retryable); // invalid number: no point trying another gateway

        $this->assertSame(153.25, $d->balance());
    }

    public function test_failover_moves_to_next_gateway(): void
    {
        MessagingSettings::put('sms.failover', ['bulksmsbd', 'sslwireless']);
        Http::fake([
            'bulksmsbd.test/*' => Http::response('Server error', 500),
            'ssl.test/api/v3/send-sms' => Http::response(['status' => 'SUCCESS', 'smsinfo' => [['reference_id' => 'R-7']]]),
        ]);

        $r = app(SmsManager::class)->send('01712345678', 'Order update');
        $this->assertTrue($r->ok);
        $this->assertSame('sslwireless', $r->driver);
        $this->assertSame('R-7', $r->messageId);
        Http::assertSentCount(2);
    }

    public function test_all_gateways_down_reports_every_error(): void
    {
        MessagingSettings::put('sms.failover', ['bulksmsbd', 'sslwireless']);
        Http::fake(['*' => Http::response('nope', 503)]);

        $r = app(SmsManager::class)->send('+8801712345678', 'x');
        $this->assertFalse($r->ok);
        $this->assertStringContainsString('bulksmsbd', $r->error);
        $this->assertStringContainsString('sslwireless', $r->error);
        $this->assertFalse(app(SmsManager::class)->send('12345', 'x')->ok); // not a BD mobile
    }

    public function test_secret_keys_are_stored_encrypted(): void
    {
        MessagingSettings::put('sms.bulksmsbd.api_key', 'SECRET-123');
        $this->assertNotSame('SECRET-123', Setting::get('messaging.sms.bulksmsbd.api_key'));
        $this->assertSame('SECRET-123', MessagingSettings::get('sms.bulksmsbd.api_key'));
    }

    public function test_balance_command(): void
    {
        config(['messaging.sms.low_balance_bdt' => 200, 'retail.alerts.email' => 'ops@example.com']);
        Http::fake(['bulksmsbd.test/api/getBalanceApi*' => Http::response(['balance' => 50])]);
        Mail::fake();

        $this->artisan('retail:sms-balance --alert')->assertSuccessful()->expectsOutputToContain('LOW');
        $this->assertDatabaseHas('notifications_log', ['event' => 'admin_alert', 'to' => 'ops@example.com']);
    }
}

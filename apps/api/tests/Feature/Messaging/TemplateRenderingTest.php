<?php

namespace Tests\Feature\Messaging;

use App\Models\MessageTemplate;
use App\Services\Messaging\Events;
use App\Services\Messaging\SmsSegments;
use App\Services\Messaging\TemplateRenderer;
use Database\Seeders\MessageTemplateSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TemplateRenderingTest extends TestCase
{
    use RefreshDatabase;

    public function test_renders_english_and_bangla_with_sections(): void
    {
        $r = app(TemplateRenderer::class);
        $data = ['order_id' => 'SR-1', 'due' => 'BDT 500'];

        $en = $r->render('order_out_for_delivery', 'sms', 'en', $data);
        $this->assertSame('Skydrop Retail: order SR-1 is out for delivery today. Please keep BDT 500 ready for the rider.', $en['body']);
        $this->assertSame('Skydrop Retail: order SR-1 is out for delivery today.', $r->render('order_out_for_delivery', 'sms', 'en', ['order_id' => 'SR-1'])['body']);

        $bn = $r->render('order_out_for_delivery', 'sms', 'bn', ['order_id' => 'SR-1', 'due' => '৳500']);
        $this->assertSame('bn', $bn['locale']);
        $this->assertStringContainsString('ডেলিভারিতে', $bn['body']);
        $this->assertStringContainsString('৳500', $bn['body']);

        $email = $r->render('payment_received', 'email', 'en', ['name' => 'Rahim', 'order_id' => 'SR-1', 'amount' => 'BDT 100']);
        $this->assertSame('Payment received for SR-1', $email['subject']);
        $this->assertStringContainsString('Your order is fully paid.', $email['body']); // {{^due}}
        $this->assertStringNotContainsString('{{', $email['body']);
    }

    public function test_database_template_overrides_default_and_inactive_suppresses(): void
    {
        $this->seed(MessageTemplateSeeder::class);
        $this->assertSame(count(Events::defaultTemplates()), MessageTemplate::query()->count());
        // Minor tracking steps have SMS off by default.
        $this->assertFalse((bool) MessageTemplate::query()->where(['key' => 'order_in_transit', 'channel' => 'sms', 'locale' => 'en'])->value('active'));

        MessageTemplate::query()->where(['key' => 'price_drop', 'channel' => 'sms', 'locale' => 'en'])->update(['body' => 'Deal: {{title}} {{price}}']);
        app(TemplateRenderer::class)->flush();
        $r = app(TemplateRenderer::class);
        $this->assertSame('Deal: X BDT 9', $r->render('price_drop', 'sms', 'en', ['title' => 'X', 'price' => 'BDT 9'])['body']);
        $this->assertNull($r->render('order_in_transit', 'sms', 'en', ['order_id' => 'A']));

        // Unknown locale falls back to English.
        $this->assertSame('en', $r->render('welcome', 'push', 'fr', ['name' => 'A'])['locale']);
    }

    public function test_default_sms_templates_stay_short(): void
    {
        $r = app(TemplateRenderer::class);
        foreach (Events::defaultTemplates() as $t) {
            if ($t['channel'] !== 'sms') {
                continue;
            }
            $body = $r->string($t['body'], Events::get($t['key'])['sample']);
            $max = $t['locale'] === 'bn' ? 3 : 2;
            $this->assertLessThanOrEqual($max, SmsSegments::count($body), "{$t['key']} {$t['locale']} is too long: {$body}");
        }
    }
}

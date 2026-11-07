<?php

namespace Tests\Feature\Assistant;

use App\Models\Assistant\AssistantMessage;
use App\Models\Marketplace;
use App\Models\Order;
use App\Models\User;
use App\Models\Setting;
use App\Services\Assistant\AssistantSettings;
use App\Services\Assistant\Pii;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class AssistantTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
        foreach (['amazon', 'flipkart', 'bigbasket'] as $m) {
            Marketplace::query()->updateOrCreate(['id' => $m], ['name' => ucfirst($m), 'source' => $m, 'enabled' => true]);
        }
        Features::set('ai_assistant', true);
        config(['assistant.api_key' => 'test-key', 'assistant.monthly_budget_usd' => 50]);
    }

    // ------------------------------------------------------------------ helpers

    /** An SSE stream like the Messages API: optional text, then optional tool_use blocks. */
    private function sse(string $text = '', array $tools = [], int $in = 1200, int $out = 80): string
    {
        $ev = fn (string $type, array $d) => "event: {$type}\ndata: ".json_encode(['type' => $type] + $d)."\n\n";
        $s = $ev('message_start', ['message' => ['id' => 'msg_1', 'model' => 'claude-sonnet-5-5', 'usage' => ['input_tokens' => $in, 'output_tokens' => 1, 'cache_read_input_tokens' => 900, 'cache_creation_input_tokens' => 0]]]);
        $s .= $ev('ping', []);
        $i = 0;
        if ($text !== '') {
            $s .= $ev('content_block_start', ['index' => $i, 'content_block' => ['type' => 'text', 'text' => '']]);
            foreach (str_split($text, 7) as $part) {
                $s .= $ev('content_block_delta', ['index' => $i, 'delta' => ['type' => 'text_delta', 'text' => $part]]);
            }
            $s .= $ev('content_block_stop', ['index' => $i]);
            $i++;
        }
        foreach ($tools as $n => [$name, $input]) {
            $s .= $ev('content_block_start', ['index' => $i, 'content_block' => ['type' => 'tool_use', 'id' => "toolu_{$n}", 'name' => $name, 'input' => (object) []]]);
            $json = json_encode((object) $input);
            foreach (str_split($json, 5) as $part) {
                $s .= $ev('content_block_delta', ['index' => $i, 'delta' => ['type' => 'input_json_delta', 'partial_json' => $part]]);
            }
            $s .= $ev('content_block_stop', ['index' => $i]);
            $i++;
        }
        $s .= $ev('message_delta', ['delta' => ['stop_reason' => $tools ? 'tool_use' : 'end_turn'], 'usage' => ['output_tokens' => $out]]);

        return $s.$ev('message_stop', []);
    }

    private function fakeClaude(string ...$bodies): void
    {
        $seq = Http::sequence();
        foreach ($bodies as $b) {
            $seq->push($b, 200, ['Content-Type' => 'text/event-stream']);
        }
        Http::fake(['api.anthropic.com/*' => $seq]);
    }

    /** @return list<array{type: string, data: array}> */
    private function events(TestResponse $r): array
    {
        $out = [];
        foreach (explode("\n\n", trim($r->streamedContent())) as $block) {
            if (preg_match('/^event: (\S+)\ndata: (.*)$/s', $block, $m)) {
                $out[] = ['type' => $m[1], 'data' => json_decode($m[2], true)];
            }
        }

        return $out;
    }

    private function ofType(array $events, string $type): array
    {
        return array_values(array_filter($events, fn ($e) => $e['type'] === $type));
    }

    private function text(array $events): string
    {
        return implode('', array_map(fn ($e) => $e['data']['delta'], $this->ofType($events, 'text')));
    }

    private function order(User $u, string $id, string $tracking): Order
    {
        $o = Order::query()->create([
            'id' => $id, 'user_id' => $u->id, 'status' => 'in_transit', 'payment_method' => 'bkash', 'payment_status' => 'paid',
            'subtotal_bdt' => 1000, 'shipping_bdt' => 180, 'total_bdt' => 1180, 'address' => ['district' => 'Dhaka'], 'tracking_code' => $tracking, 'fx_rate' => 1.42,
        ]);
        $o->items()->create(['product_id' => 'P1', 'market' => 'amazon', 'title' => 'Thing', 'qty' => 1, 'unit_bdt' => 1000, 'unit_inr' => 700, 'weight_kg' => 0.4]);
        $o->events()->create(['status' => 'placed', 'at' => now()]);

        return $o;
    }

    /** Tool results sent back to Claude in the n-th request (0-based). */
    private function toolResults(int $n): array
    {
        $req = Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), 'anthropic'))[$n][0];
        $last = end($req->data()['messages']);

        return array_map(fn ($b) => json_decode($b['content'], true), $last['content']);
    }

    // -------------------------------------------------------------------- tests

    public function test_tool_loop_searches_and_streams_product_cards(): void
    {
        $p = $this->product('B0EARBUD01', 1500, 'amazon', 'electronics', ['title' => 'boAt Airdopes wireless earbuds', 'rating' => 4.2, 'reviews' => 900]);
        $this->product('B0CABLE001', 300, 'amazon', 'electronics', ['title' => 'USB-C cable']);
        $this->fakeClaude(
            $this->sse('Let me look.', [['search_products', ['query' => 'earbuds', 'max_price_bdt' => 5000]]]),
            $this->sse('The boAt Airdopes are a great pick at ৳'.number_format($this->bdt($p)).'.'),
        );

        $r = $this->post('/api/v1/assistant/chat', ['message' => 'best earbuds under 5000 taka', 'context' => ['productId' => 'B0CABLE001']]);
        $r->assertOk();
        $this->assertStringContainsString('text/event-stream', $r->headers->get('Content-Type'));
        $ev = $this->events($r);

        $this->assertSame('meta', $ev[0]['type']);
        $this->assertFalse($ev[0]['data']['degraded']);
        $cards = $this->ofType($ev, 'products');
        $this->assertCount(1, $cards);
        $this->assertSame(['B0EARBUD01'], array_column($cards[0]['data']['items'], 'id'));
        $this->assertSame($this->bdt($p), $cards[0]['data']['items'][0]['priceBdt']);
        $this->assertStringContainsString("Let me look.\n\nThe boAt Airdopes", $this->text($ev));
        $done = $this->ofType($ev, 'done')[0]['data'];
        $this->assertSame(9, $done['remaining']);

        // Two calls: the second carries the tool result; system prompt and tools are cached.
        Http::assertSentCount(2);
        $first = Http::recorded()[0][0];
        $this->assertSame('test-key', $first->header('x-api-key')[0]);
        $this->assertSame('2023-06-01', $first->header('anthropic-version')[0]);
        $this->assertSame('claude-sonnet-5-5', $first['model']);
        $this->assertSame(['type' => 'ephemeral'], $first['system'][0]['cache_control']);
        $this->assertSame(['type' => 'ephemeral'], end($first->data()['tools'])['cache_control']);
        $this->assertStringContainsString('B0CABLE001', $first['system'][1]['text']); // page context
        $this->assertSame('B0EARBUD01', $this->toolResults(1)[0]['products'][0]['id']);

        $reply = AssistantMessage::query()->where('role', 'assistant')->firstOrFail();
        $this->assertSame($done['messageId'], $reply->id);
        $this->assertSame(2400, $reply->input_tokens);
        $this->assertSame(1800, $reply->cache_read_tokens);
        $this->assertGreaterThan(0, $reply->cost_usd);
        $this->assertSame('search_products', $reply->tools[0]['name']);
        $this->assertSame('products', $reply->cards[0]['type']);
    }

    public function test_tool_rounds_are_capped_at_five(): void
    {
        $this->product('B0EARBUD01', 1500);
        $loop = $this->sse('', [['search_products', ['query' => 'test']]]);
        $this->fakeClaude($loop, $loop, $loop, $loop, $loop, $this->sse('Done.'));

        $ev = $this->events($this->post('/api/v1/assistant/chat', ['message' => 'test'])->assertOk());
        Http::assertSentCount(6);
        $this->assertSame(['type' => 'none'], Http::recorded()[5][0]['tool_choice']);
        $this->assertSame('Done.', $this->text($ev));
    }

    public function test_budget_hard_stop_degrades_to_plain_search(): void
    {
        $this->product('B0EARBUD01', 1500, 'amazon', 'electronics', ['title' => 'Wireless earbuds Pro']);
        AssistantSettings::put('monthly_budget_usd', 1);
        AssistantMessage::query()->create(['conversation_id' => '00000000-0000-0000-0000-000000000000', 'role' => 'assistant', 'content' => 'x', 'cost_usd' => 1.5]);
        Http::fake();

        $ev = $this->events($this->post('/api/v1/assistant/chat', ['message' => 'show me wireless earbuds'])->assertOk());
        Http::assertNothingSent();
        $this->assertTrue($ev[0]['data']['degraded']);
        $this->assertSame('B0EARBUD01', $this->ofType($ev, 'products')[0]['data']['items'][0]['id']);
        $this->assertStringContainsString('basic mode', $this->text($ev));
        $this->assertTrue(AssistantMessage::query()->where('role', 'assistant')->latest('id')->first()->degraded);
        $this->getJson('/api/v1/assistant/config')->assertOk()->assertJsonPath('basic', true);
    }

    public function test_api_failure_degrades_gracefully(): void
    {
        $this->product('B0EARBUD01', 1500, 'amazon', 'electronics', ['title' => 'Wireless earbuds Pro']);
        Http::fake(['api.anthropic.com/*' => Http::response(['type' => 'error', 'error' => ['type' => 'invalid_request_error', 'message' => 'bad']], 400)]);

        $ev = $this->events($this->post('/api/v1/assistant/chat', ['message' => 'earbuds'])->assertOk());
        $this->assertNotEmpty($this->ofType($ev, 'products'));
        $this->assertSame('api_400', AssistantMessage::query()->where('role', 'assistant')->first()->error);
    }

    public function test_overloaded_primary_retries_on_fallback_model(): void
    {
        Http::fake(['api.anthropic.com/*' => Http::sequence()
            ->push(['type' => 'error', 'error' => ['type' => 'overloaded_error', 'message' => 'Overloaded']], 529)
            ->push($this->sse('Hello!'), 200, ['Content-Type' => 'text/event-stream'])]);

        $ev = $this->events($this->post('/api/v1/assistant/chat', ['message' => 'hi'])->assertOk());
        $this->assertSame('Hello!', $this->text($ev));
        $this->assertSame('claude-haiku-5-5', Http::recorded()[1][0]['model']);
    }

    public function test_daily_rate_limit_for_guests_and_users(): void
    {
        AssistantSettings::put('limits.guest', 2);
        AssistantSettings::put('limits.user', 3);
        $this->fakeClaude($this->sse('a'), $this->sse('b'), $this->sse('c'), $this->sse('d'), $this->sse('e'));

        $this->post('/api/v1/assistant/chat', ['message' => 'hi'])->assertOk()->streamedContent();
        $this->post('/api/v1/assistant/chat', ['message' => 'hi'])->assertOk()->streamedContent();
        $this->postJson('/api/v1/assistant/chat', ['message' => 'hi'])->assertStatus(429)
            ->assertJsonPath('code', 'rate_limited')->assertJsonPath('limit', 2);

        $this->shopper(); // signed-in shoppers have their own, larger allowance
        $this->getJson('/api/v1/assistant/config')->assertJsonPath('limit', 3)->assertJsonPath('remaining', 3);
        $this->post('/api/v1/assistant/chat', ['message' => 'hi'])->assertOk()->streamedContent();
    }

    public function test_auth_only_tools_refuse_guests(): void
    {
        $this->fakeClaude(
            $this->sse('', [['get_my_orders', []], ['track_order', ['id' => 'SR-ABC123']]]),
            $this->sse('Please sign in to see your orders.'),
        );

        $ev = $this->events($this->post('/api/v1/assistant/chat', ['message' => 'where is my order?'])->assertOk());
        $this->assertCount(2, $this->ofType($ev, 'login'));
        $this->assertEmpty($this->ofType($ev, 'orders'));
        $results = $this->toolResults(1);
        $this->assertSame('login_required', $results[0]['error']);
        $this->assertSame('login_required', $results[1]['error']);
        $this->assertTrue(Http::recorded()[1][0]->data()['messages'][2]['content'][0]['is_error']);
    }

    public function test_user_cannot_access_another_users_orders(): void
    {
        $other = $this->shopper(['phone' => '+8801812345678', 'name' => 'Other']);
        $theirs = $this->order($other, 'SR-THEIRS', 'SD000000001');
        $me = $this->shopper(['phone' => '+8801912345678', 'name' => 'Karim']);
        $mine = $this->order($me, 'SR-MINE01', 'SD000000002');

        $this->fakeClaude(
            $this->sse('', [['track_order', ['id' => $theirs->id]], ['track_order', ['id' => $mine->tracking_code]], ['get_my_orders', []]]),
            $this->sse('Your order is on its way.'),
        );
        $ev = $this->events($this->post('/api/v1/assistant/chat', ['message' => 'track '.$theirs->id])->assertOk());

        $results = $this->toolResults(1);
        $this->assertSame('order_not_found', $results[0]['error']);
        $this->assertSame($mine->id, $results[1]['id']);
        $this->assertSame([$mine->id], array_column($results[2]['orders'], 'id'));
        $this->assertSame($mine->id, $this->ofType($ev, 'order')[0]['data']['id']);
        $this->assertStringNotContainsString($theirs->id, json_encode($this->ofType($ev, 'orders')));
    }

    public function test_cart_suggestion_is_an_action_card_only(): void
    {
        $p = $this->product('B0SHIRT001', 900, 'flipkart', 'fashion');
        $this->fakeClaude($this->sse('', [['add_to_cart_suggestion', ['productId' => 'B0SHIRT001', 'variant' => 'M', 'qty' => 2]]]), $this->sse('Tap to add it.'));
        $u = $this->shopper();

        $ev = $this->events($this->post('/api/v1/assistant/chat', ['message' => 'add the shirt in M'])->assertOk());
        $card = $this->ofType($ev, 'cart')[0]['data'];
        $this->assertSame(['B0SHIRT001', 'M', 2, $this->bdt($p)], [$card['product']['id'], $card['variant'], $card['qty'], $card['product']['priceBdt']]);
        $this->assertDatabaseMissing('carts', ['user_id' => $u->id]);
    }

    public function test_non_streaming_variant_and_conversation_history(): void
    {
        $this->fakeClaude($this->sse('Hi there!'), $this->sse('Second answer.'));
        $r = $this->postJson('/api/v1/assistant/chat?stream=0', ['message' => 'Hello, call me at 01712345678', 'client' => 'mobile'])->assertOk()
            ->assertJsonPath('text', 'Hi there!')->assertJsonPath('remaining', 9);
        $conv = $r->json('conversationId');

        $this->postJson('/api/v1/assistant/chat?stream=0', ['message' => 'and again', 'conversationId' => $conv])->assertOk()
            ->assertJsonPath('conversationId', $conv);
        $second = Http::recorded()[1][0]->data()['messages'];
        $this->assertSame(['user', 'assistant', 'user'], array_column($second, 'role'));
        $this->assertStringContainsString('[phone]', $second[0]['content']); // PII scrubbed before storage/replay
        $this->assertDatabaseMissing('assistant_messages', ['content' => 'Hello, call me at 01712345678']);

        $this->getJson("/api/v1/assistant/conversations/{$conv}")->assertOk()->assertJsonCount(4, 'messages');
        $this->shopper(); // a signed-in user cannot read a guest conversation
        $this->getJson("/api/v1/assistant/conversations/{$conv}")->assertNotFound();
    }

    public function test_feedback_and_clear(): void
    {
        $this->fakeClaude($this->sse('Answer'));
        $r = $this->postJson('/api/v1/assistant/chat?stream=0', ['message' => 'hi'])->assertOk();

        $this->postJson('/api/v1/assistant/feedback', ['messageId' => $r->json('messageId'), 'rating' => 'down', 'comment' => 'mail me a@b.com'])->assertOk();
        $this->postJson('/api/v1/assistant/feedback', ['messageId' => $r->json('messageId'), 'rating' => 'up'])->assertOk();
        $this->assertDatabaseCount('assistant_feedback', 1);
        $this->assertDatabaseHas('assistant_feedback', ['message_id' => $r->json('messageId'), 'rating' => 1]);

        $this->deleteJson('/api/v1/assistant/conversations/'.$r->json('conversationId'))->assertOk();
        $this->assertDatabaseMissing('assistant_messages', ['content' => 'Answer']);
    }

    public function test_feature_switch_off_hides_the_assistant(): void
    {
        Features::set('ai_assistant', false);
        Http::fake();
        $this->postJson('/api/v1/assistant/chat', ['message' => 'hi'])->assertNotFound()->assertJsonPath('error', 'feature_disabled');
        Http::assertNothingSent();
    }

    public function test_delivery_estimate_with_holiday_cutoffs(): void
    {
        $this->travelTo('2027-02-01 10:00:00');
        Setting::put('assistant.holidays', "2027-03-10 | Eid-ul-Fitr | Approx.\n2027-02-05 | Too soon");
        $this->fakeClaude($this->sse('', [['delivery_estimate', []]]), $this->sse('Order by 26 Feb.'));

        $ev = $this->events($this->post('/api/v1/assistant/chat', ['message' => 'eid er age pabo?'])->assertOk());
        $d = $this->ofType($ev, 'delivery')[0]['data'];
        $this->assertSame([7, 12], [$d['minDays'], $d['maxDays']]);
        $this->assertSame(['Eid-ul-Fitr', '2027-02-26', 'on_time'], [$d['cutoffs'][0]['name'], $d['cutoffs'][0]['orderBy'], $d['cutoffs'][0]['status']]);
        $this->assertSame('too_late', $d['cutoffs'][1]['status']);
    }

    public function test_pii_scrubber(): void
    {
        $this->assertSame('call [phone] or [phone], mail [email]', Pii::scrub('call 01712-345678 or +8801812345678, mail rahim@example.com'));
        $this->assertSame('+88017•••••678', Pii::maskPhone('+8801712345678'));
    }
}

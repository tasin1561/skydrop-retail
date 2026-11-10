<?php

namespace Tests\Feature\Tracking;

use App\Models\Order;
use App\Models\ProductDetail;
use App\Models\Setting;
use App\Models\TrackingEvent;
use App\Services\Commerce\OrderWorkflow;
use App\Services\PricingService;
use App\Services\Tracking\Attribution;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Tests\Feature\Commerce\CommerceHelpers;
use Tests\TestCase;

class TrackingTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
        config([
            'retail.tracking.driver' => 'direct',
            'retail.tracking.purchase_on' => 'paid',
            'retail.tracking.send_delivered' => false,
            'retail.tracking.meta' => ['pixel_id' => '111', 'access_token' => 'meta-token', 'test_event_code' => 'TEST1', 'api_version' => 'v21.0'],
            'retail.tracking.ga4' => ['measurement_id' => 'G-TEST', 'api_secret' => 'ga-secret'],
            'retail.tracking.tiktok' => ['pixel_id' => 'TT1', 'access_token' => 'tt-token', 'test_event_code' => null],
            'retail.tracking.delivro' => ['endpoint' => 'https://delivro.test/ingest', 'key' => 'dl-key'],
            'retail.tracking.feed_key' => null,
        ]);
        Http::fake([
            'graph.facebook.com/*' => Http::response(['events_received' => 1]),
            'www.google-analytics.com/*' => Http::response('', 204),
            'business-api.tiktok.com/*' => Http::response(['code' => 0, 'message' => 'OK']),
            'delivro.test/*' => Http::response(['ok' => true]),
        ]);
    }

    private function metaRequests(): array
    {
        return Http::recorded(fn (HttpRequest $r) => str_contains($r->url(), 'graph.facebook.com'))->all();
    }

    public function test_track_queues_one_send_per_destination_with_hashed_user_data(): void
    {
        $this->shopper(['email' => ' Rahim@Example.com ']); // Sanctum token user: +8801712345678

        $this->withHeader('User-Agent', 'TestUA/1.0')->postJson('/api/v1/track', [
            'event' => 'AddToCart', 'eventId' => 'evt-123', 'url' => 'http://localhost:3000/p/A1', 'value' => 1500,
            'items' => [['id' => 'A1', 'title' => 'Earbuds', 'price' => 1500, 'qty' => 1]],
            'fbp' => 'fb.1.1700000000.123', 'fbc' => 'fb.1.1700000000.abc', 'gaClientId' => '123.456', 'ttclid' => 'tt-click',
        ])->assertStatus(202)->assertJsonPath('queued', 3);

        $this->assertSame(3, TrackingEvent::query()->where('event_id', 'evt-123')->where('status', 'sent')->count());

        $phone = hash('sha256', '8801712345678');
        Http::assertSent(function (HttpRequest $r) use ($phone) {
            if (! str_contains($r->url(), 'graph.facebook.com/v21.0/111/events')) {
                return false;
            }
            $d = $r['data'][0];

            return str_contains($r->url(), 'access_token=meta-token') && $r['test_event_code'] === 'TEST1'
                && $d['event_name'] === 'AddToCart' && $d['event_id'] === 'evt-123' && $d['action_source'] === 'website'
                && $d['user_data']['ph'] === [$phone] && $d['user_data']['em'] === [hash('sha256', 'rahim@example.com')]
                && $d['user_data']['fbp'] === 'fb.1.1700000000.123' && $d['user_data']['client_user_agent'] === 'TestUA/1.0'
                && ! empty($d['user_data']['client_ip_address'])
                && $d['custom_data']['currency'] === 'BDT' && $d['custom_data']['content_ids'] === ['A1']
                && $d['custom_data']['content_type'] === 'product' && (float) $d['custom_data']['value'] === 1500.0;
        });
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'tiktok.com')
            && $r->header('Access-Token')[0] === 'tt-token'
            && $r['data'][0]['event_id'] === 'evt-123'
            && $r['data'][0]['user']['phone'] === hash('sha256', '+8801712345678')
            && $r['data'][0]['user']['ttclid'] === 'tt-click');
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'google-analytics.com/mp/collect')
            && str_contains($r->url(), 'api_secret=ga-secret')
            && $r['client_id'] === '123.456' && $r['events'][0]['name'] === 'add_to_cart'
            && $r['events'][0]['params']['currency'] === 'BDT');

        // Stored log never holds the plain phone or email.
        $payload = json_encode(TrackingEvent::query()->first()->payload);
        $this->assertStringNotContainsString('1712345678', $payload);
        $this->assertStringNotContainsString('example.com', $payload);

        // Same event_id again: deduped, nothing new sent.
        $this->postJson('/api/v1/track', ['event' => 'AddToCart', 'eventId' => 'evt-123'])->assertStatus(202)->assertJsonPath('queued', 0);
        $this->assertCount(1, $this->metaRequests());
    }

    public function test_track_without_token_uses_body_user_data_and_validates(): void
    {
        $this->postJson('/api/v1/track', ['event' => 'Lead', 'eventId' => 'lead-1', 'user' => ['phone' => '01812-345678']])
            ->assertStatus(202);
        Http::assertSent(fn (HttpRequest $r) => str_contains($r->url(), 'graph.facebook.com')
            && $r['data'][0]['user_data']['ph'] === [hash('sha256', '8801812345678')]);

        $this->postJson('/api/v1/track', ['event' => 'Purchase', 'eventId' => 'x'])->assertStatus(422); // Purchase is server-only
        $this->postJson('/api/v1/track', ['event' => 'Lead', 'eventId' => 'bad id!'])->assertStatus(422);
    }

    public function test_driver_off_sends_nothing_and_delivro_driver_posts_generic_event(): void
    {
        config(['retail.tracking.driver' => 'off']);
        $this->postJson('/api/v1/track', ['event' => 'Search', 'eventId' => 's-1', 'search' => 'earbuds'])->assertStatus(202)->assertJsonPath('queued', 0);
        Http::assertNothingSent();

        config(['retail.tracking.driver' => 'delivro']);
        $this->postJson('/api/v1/track', ['event' => 'Search', 'eventId' => 's-2', 'search' => 'earbuds'])->assertJsonPath('queued', 1);
        Http::assertSent(fn (HttpRequest $r) => $r->url() === 'https://delivro.test/ingest'
            && $r->header('Authorization')[0] === 'Bearer dl-key'
            && $r['event_name'] === 'Search' && $r['event_id'] === 's-2' && $r['search_string'] === 'earbuds');
        $this->assertDatabaseHas('tracking_events', ['event_id' => 's-2', 'destination' => 'delivro', 'status' => 'sent']);
    }

    public function test_server_errors_are_retried_and_client_errors_fail(): void
    {
        config(['retail.tracking.driver' => 'direct', 'retail.tracking.ga4.api_secret' => null, 'retail.tracking.tiktok.access_token' => null]);
        Http::swap(new HttpFactory);
        Http::fake(['graph.facebook.com/*' => Http::sequence()->push('down', 503)->push(['error' => ['message' => 'bad token']], 400)]);

        $this->postJson('/api/v1/track', ['event' => 'ViewContent', 'eventId' => 'v-1'])->assertStatus(202);
        $row = TrackingEvent::query()->where('event_id', 'v-1')->firstOrFail();
        $this->assertSame('queued', $row->status); // released for a retry with backoff
        $this->assertStringContainsString('503', (string) $row->error);

        $this->postJson('/api/v1/track', ['event' => 'ViewContent', 'eventId' => 'v-2'])->assertStatus(202);
        $this->assertDatabaseHas('tracking_events', ['event_id' => 'v-2', 'status' => 'failed', 'response_code' => 400]);
    }

    public function test_attribution_is_saved_on_the_order(): void
    {
        $this->shopper();
        $p = $this->product('A1', 1000);
        $id = $this->withHeader('User-Agent', 'ShopperUA')->postJson('/api/v1/orders', [
            'items' => [['productId' => 'A1', 'qty' => 1, 'unitBdt' => $this->bdt($p)]],
            'address' => $this->addressPayload(), 'payment' => 'bkash',
            'attribution' => [
                'first' => ['utm_source' => 'facebook', 'utm_medium' => 'cpc', 'utm_campaign' => 'eid-sale', 'fbclid' => 'IwAR1', 'landing' => '/p/A1', 'evil' => 'x'],
                'last' => ['utm_source' => 'google', 'gclid' => 'G-CLICK'],
                'fbp' => 'fb.1.1.2', 'fbc' => 'fb.1.1.IwAR1', 'gaClientId' => '99.88', 'consent' => 'all', 'junk' => ['a'],
            ],
        ])->assertCreated()->json('order.id');

        $a = Order::query()->findOrFail($id)->attribution;
        $this->assertSame('facebook', $a['first']['utm_source']);
        $this->assertSame('G-CLICK', $a['last']['gclid']);
        $this->assertSame('fb.1.1.2', $a['fbp']);
        $this->assertSame('ShopperUA', $a['ua']);
        $this->assertNotEmpty($a['ip']);
        $this->assertArrayNotHasKey('evil', $a['first']);
        $this->assertArrayNotHasKey('junk', $a);
        $this->assertSame(['source' => 'google', 'medium' => null, 'campaign' => null], Attribution::source($a));
    }

    public function test_purchase_uses_deterministic_event_id_and_is_sent_once(): void
    {
        config(['retail.tracking.ga4.api_secret' => null, 'retail.tracking.tiktok.access_token' => null]); // Meta only
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $o->forceFill(['attribution' => ['fbp' => 'fb.1.9.9', 'ip' => '203.0.113.5', 'ua' => 'UA', 'consent' => 'all']])->save();
        $this->assertCount(0, $this->metaRequests()); // purchase_on=paid: nothing at placement

        $o->forceFill(['payment_status' => 'partial'])->save();
        $o->forceFill(['payment_status' => 'paid'])->save(); // second paid-ish change: deduped

        $sent = $this->metaRequests();
        $this->assertCount(1, $sent);
        $d = $sent[0][0]['data'][0];
        $this->assertSame('Purchase', $d['event_name']);
        $this->assertSame('purchase_'.$o->id, $d['event_id']);
        $this->assertSame($o->id, $d['custom_data']['order_id']);
        $this->assertSame((float) $o->total_bdt, (float) $d['custom_data']['value']);
        $this->assertSame('fb.1.9.9', $d['user_data']['fbp']);
        $this->assertSame('203.0.113.5', $d['user_data']['client_ip_address']);
        $this->assertSame([hash('sha256', '8801712345678')], $d['user_data']['ph']);
        $this->assertDatabaseHas('tracking_events', ['event_id' => 'purchase_'.$o->id, 'order_id' => $o->id, 'destination' => 'meta', 'status' => 'sent']);
    }

    public function test_purchase_on_placed_and_essential_consent(): void
    {
        config(['retail.tracking.purchase_on' => 'placed', 'retail.tracking.ga4.api_secret' => null, 'retail.tracking.tiktok.access_token' => null]);
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $this->assertDatabaseHas('tracking_events', ['event_id' => 'purchase_'.$o->id, 'event' => 'Purchase']);
        $this->assertSame('A1', $this->metaRequests()[0][0]['data'][0]['custom_data']['content_ids'][0]); // items present after commit

        $o2 = $this->placeOrder($u, [$this->product('A2', 500)], ['attribution' => ['consent' => 'essential']]);
        $this->assertDatabaseMissing('tracking_events', ['order_id' => $o2->id]);
    }

    public function test_purchase_on_delivered_fires_from_the_workflow_with_delivered_event(): void
    {
        config(['retail.tracking.purchase_on' => 'delivered', 'retail.tracking.send_delivered' => true,
            'retail.tracking.ga4.api_secret' => null, 'retail.tracking.tiktok.access_token' => null]);
        $u = $this->shopper();
        $o = $this->placeOrder($u, [$this->product('A1', 1000)]);
        $o->forceFill(['payment_status' => 'paid'])->save();
        $wf = app(OrderWorkflow::class);
        foreach (['verified', 'purchased', 'out_for_delivery'] as $s) {
            $wf->transition($o->refresh(), $s, 'admin');
        }
        $this->assertCount(0, $this->metaRequests());

        $wf->transition($o->refresh(), 'delivered', 'skydrop');
        $names = array_map(fn ($r) => $r[0]['data'][0]['event_name'], $this->metaRequests());
        $this->assertEqualsCanonicalizing(['Purchase', 'Delivered'], $names);
        $this->assertDatabaseHas('tracking_events', ['event_id' => 'delivered_'.$o->id, 'event' => 'Delivered']);
    }

    public function test_tracking_config_returns_public_ids_only(): void
    {
        Setting::put('tracking.meta.pixel_id', '222'); // admin override wins over config
        $this->getJson('/api/v1/tracking/config')->assertOk()
            ->assertExactJson(['metaPixelId' => '222', 'ga4Id' => 'G-TEST', 'tiktokPixelId' => 'TT1', 'gtmId' => null, 'server' => true]);
    }

    public function test_feeds_list_visible_priced_products(): void
    {
        $a = $this->product('A1', 1000, 'amazon', 'electronics', ['mrp_inr' => 2000, 'brand' => 'Boat']);
        $this->product('F1', 500, 'flipkart', 'fashion', ['mrp_inr' => 0, 'brand' => '', 'in_stock' => false]);
        $this->product('H1', 900, 'amazon', 'home', ['hidden' => true]);
        $this->product('Z1', 0, 'amazon', 'home');
        ProductDetail::query()->create(['product_id' => 'A1', 'category_path' => ['Electronics', 'Headphones'], 'bullets' => ['Great sound', 'Long battery']]);

        $pricing = app(PricingService::class);
        $sale = $pricing->productBdt($a->fresh());
        $mrp = $pricing->priceBdt(2000, 'electronics');

        config(['retail.tracking.feed_key' => 'feed-test-key']);
        $csv = $this->get('/api/v1/feeds/meta.csv?key=feed-test-key')->assertOk()->assertHeader('Content-Type', 'text/csv; charset=utf-8')->getContent();
        $rows = array_map('str_getcsv', array_filter(explode("\n", trim($csv))));
        $head = array_shift($rows);
        $this->assertSame(['id', 'title', 'description', 'availability', 'condition', 'price', 'sale_price', 'link', 'image_link', 'brand',
            'google_product_category', 'product_type', 'custom_label_0'], $head);
        $byId = collect($rows)->map(fn ($r) => array_combine($head, $r))->keyBy('id');
        $this->assertEqualsCanonicalizing(['A1', 'F1'], $byId->keys()->all()); // hidden and unpriced skipped
        $this->assertSame("{$mrp} BDT", $byId['A1']['price']);
        $this->assertSame("{$sale} BDT", $byId['A1']['sale_price']);
        $this->assertSame('http://localhost:3000/p/A1', $byId['A1']['link']);
        $this->assertSame('Electronics > Headphones', $byId['A1']['product_type']);
        $this->assertSame('Great sound Long battery', $byId['A1']['description']);
        $this->assertSame('', $byId['F1']['sale_price']);
        $this->assertSame('out of stock', $byId['F1']['availability']);
        $this->assertSame('Skydrop Retail', $byId['F1']['brand']); // no product brand: never the store's name (Amazon/Flipkart/Myntra aren't the brand)
        $this->assertSame('flipkart', $byId['F1']['custom_label_0']);

        $xml = $this->get('/api/v1/feeds/google.xml?store=amazon&key=feed-test-key')->assertOk()->getContent();
        $doc = simplexml_load_string($xml);
        $items = $doc->channel->item;
        $this->assertCount(1, $items);
        $g = $items[0]->children('http://base.google.com/ns/1.0');
        $this->assertSame('A1', (string) $g->id);
        $this->assertSame('new', (string) $g->condition);
        $this->assertSame('Electronics', (string) $g->google_product_category);
        $this->assertSame('amazon', (string) $g->custom_label_0);
    }

    public function test_feed_key_protects_feeds(): void
    {
        config(['retail.tracking.feed_key' => 'sekret']);
        $this->get('/api/v1/feeds/meta.csv')->assertForbidden();
        $this->get('/api/v1/feeds/google.xml?key=wrong')->assertForbidden();
        $this->get('/api/v1/feeds/google.xml?key=sekret')->assertOk();
        $this->get('/api/v1/feeds/meta.csv?key=sekret&store=nope')->assertStatus(422);
    }

    public function test_feature_switches_turn_tracking_and_feeds_off(): void
    {
        Features::set('tracking', false);
        Features::set('feeds', false);
        $this->postJson('/api/v1/track', ['event' => 'Lead', 'eventId' => 'off-1'])->assertNotFound()->assertJsonPath('feature', 'tracking');
        $this->getJson('/api/v1/tracking/config')->assertNotFound();
        $this->get('/api/v1/feeds/meta.csv')->assertNotFound();

        // Server-side Purchase is not sent either.
        config(['retail.tracking.purchase_on' => 'placed']);
        $o = $this->placeOrder($this->shopper(), [$this->product('A1', 1000)]);
        $this->assertDatabaseMissing('tracking_events', ['order_id' => $o->id]);
        Http::assertNothingSent();

        Features::set('tracking', true);
        $this->postJson('/api/v1/track', ['event' => 'Lead', 'eventId' => 'on-1'])->assertStatus(202);
    }
}

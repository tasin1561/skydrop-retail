<?php

namespace App\Services\Assistant;

use App\Models\Order;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Services\Catalog\CatalogQuery;
use App\Services\Cms\RestrictionService;
use App\Services\PricingService;
use App\Support\Features;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Tools the model may call. Each returns ['result' => compact JSON for the model, 'events' => [[type, data], ...]]
 * where events are the structured cards streamed to the shopper (products, compare, order, cart, delivery, login).
 * Read-only: nothing here mutates the cart; add_to_cart_suggestion only returns an action card.
 */
class AssistantTools
{
    public const MARKETS = ['amazon' => 'amazon.in', 'flipkart' => 'Flipkart', 'bigbasket' => 'BigBasket'];

    public function __construct(private CatalogQuery $catalog, private PricingService $pricing) {}

    /** Tool definitions for the Messages API (the last one carries cache_control so the whole list is cached). */
    public function definitions(): array
    {
        $store = ['type' => 'string', 'enum' => ['all', 'amazon', 'flipkart', 'bigbasket'], 'description' => 'amazon = amazon.in, flipkart = Flipkart (fashion), bigbasket = BigBasket (grocery). Default all.'];
        $id = ['type' => 'string', 'description' => 'Product id from a previous tool result'];
        $tools = [
            ['name' => 'search_products', 'description' => 'Search the Skydrop Retail catalog (products we can buy in India and deliver to Bangladesh). Returns up to 8 products with BDT prices. Always use this before recommending anything. Use short English keywords even when the shopper writes Bangla.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'query' => ['type' => 'string', 'description' => 'Short English keywords, e.g. "wireless earbuds"'],
                    'store' => $store,
                    'max_price_bdt' => ['type' => 'integer', 'description' => 'Budget ceiling in BDT'],
                    'min_rating' => ['type' => 'number', 'description' => '1-5'],
                    'category' => ['type' => 'string', 'enum' => ['electronics', 'fashion', 'beauty', 'grocery', 'home']],
                    'sort' => ['type' => 'string', 'enum' => ['relevance', 'price_asc', 'price_desc', 'rating', 'discount']],
                ], 'required' => ['query']]],
            ['name' => 'get_product', 'description' => 'Full details for one product: features, specs, variants (sizes/colours), offers, stock and BDT price.',
                'input_schema' => ['type' => 'object', 'properties' => ['id' => $id], 'required' => ['id']]],
            ['name' => 'compare_products', 'description' => 'Side-by-side comparison of 2-4 products (price, rating, key specs). Shows a comparison table to the shopper.',
                'input_schema' => ['type' => 'object', 'properties' => ['ids' => ['type' => 'array', 'items' => ['type' => 'string'], 'minItems' => 2, 'maxItems' => 4]], 'required' => ['ids']]],
            ['name' => 'delivery_estimate', 'description' => 'Delivery time to Bangladesh (7-12 days) with upcoming Eid/holiday order-by dates, and whether a product can ship to Bangladesh.',
                'input_schema' => ['type' => 'object', 'properties' => ['productId' => $id]]],
            ['name' => 'get_my_orders', 'description' => "The signed-in shopper's recent orders with status. Requires sign-in.",
                'input_schema' => ['type' => 'object', 'properties' => (object) []]],
            ['name' => 'track_order', 'description' => 'Status and timeline of one of the signed-in shopper\'s orders, by order id (SR-XXXXXX) or tracking code (SD...). Requires sign-in.',
                'input_schema' => ['type' => 'object', 'properties' => ['id' => ['type' => 'string']], 'required' => ['id']]],
            ['name' => 'add_to_cart_suggestion', 'description' => 'Show an "Add to cart" button for a product. Does not change the cart; the shopper taps to add. Use when they say they want to buy it.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'productId' => $id, 'variant' => ['type' => 'string', 'description' => 'Variant label, e.g. "M" or "Black"'], 'qty' => ['type' => 'integer', 'minimum' => 1, 'maximum' => 10],
                ], 'required' => ['productId']]],
        ];
        if ($this->priceHistoryAvailable()) {
            $tools[] = ['name' => 'get_price_history', 'description' => 'Recent price history of a product in BDT (lowest, highest, current) to judge if now is a good time to buy.',
                'input_schema' => ['type' => 'object', 'properties' => ['id' => $id], 'required' => ['id']]];
        }
        if ($this->productRequestsAvailable()) {
            $tools[] = ['name' => 'create_product_request', 'description' => 'Ask our team to source a product that is not in the catalog. Requires sign-in. Use only after search found nothing suitable and the shopper agrees.',
                'input_schema' => ['type' => 'object', 'properties' => [
                    'title' => ['type' => 'string', 'description' => 'What the shopper wants'],
                    'url' => ['type' => 'string', 'description' => 'amazon.in / Flipkart / BigBasket link if given'],
                    'note' => ['type' => 'string', 'description' => 'Size, colour, budget, quantity'],
                ], 'required' => ['title']]];
        }
        $tools[count($tools) - 1]['cache_control'] = ['type' => 'ephemeral'];

        return $tools;
    }

    public function priceHistoryAvailable(): bool
    {
        return $this->feature('price_history') && Schema::hasTable('price_history');
    }

    public function productRequestsAvailable(): bool
    {
        return $this->feature('product_requests') && Schema::hasTable('product_requests');
    }

    private function feature(string $key): bool
    {
        return ! class_exists(Features::class) || Features::enabled($key);
    }

    /** @return array{result: array<string, mixed>, events: list<array{0: string, 1: array<string, mixed>}>} */
    public function run(string $name, array $input, AssistantContext $ctx): array
    {
        try {
            return match ($name) {
                'search_products' => $this->search($input, $ctx),
                'get_product' => $this->product($input),
                'compare_products' => $this->compare($input),
                'get_price_history' => $this->history($input),
                'delivery_estimate' => $this->delivery($input),
                'get_my_orders' => $this->orders($ctx),
                'track_order' => $this->track($input, $ctx),
                'add_to_cart_suggestion' => $this->cart($input),
                'create_product_request' => $this->request($input, $ctx),
                default => $this->fail('unknown_tool'),
            };
        } catch (Throwable $e) {
            report($e);

            return $this->fail('tool_failed');
        }
    }

    // ------------------------------------------------------------------ tools

    private function search(array $in, AssistantContext $ctx): array
    {
        $store = $in['store'] ?? $ctx->store;
        $res = $this->catalog->search([
            'q' => mb_substr(trim((string) ($in['query'] ?? '')), 0, 80),
            'store' => in_array($store, ['amazon', 'flipkart', 'bigbasket'], true) ? $store : 'all',
            'category' => $in['category'] ?? null,
            'sort' => $in['sort'] ?? 'relevance',
            'max' => isset($in['max_price_bdt']) ? (int) $in['max_price_bdt'] : null,
            'rating' => isset($in['min_rating']) ? (float) $in['min_rating'] : null,
            'perPage' => 8,
        ]);
        $items = $res['items']->map(fn (Product $p) => $this->card($p))->values()->all();

        return [
            'result' => ['total' => $res['total'], 'products' => array_map(fn ($c) => $this->forModel($c), $items)],
            'events' => $items ? [['products', ['query' => (string) ($in['query'] ?? ''), 'total' => $res['total'], 'items' => $items]]] : [],
        ];
    }

    private function product(array $in): array
    {
        $p = $this->find($in['id'] ?? null);
        if (! $p) {
            return $this->fail('not_found');
        }
        $d = $p->details;
        $card = $this->card($p);
        $variants = collect((array) ($d?->variants ?? []))->take(30)->map(fn ($v) => array_filter([
            'label' => $v['label'] ?? null, 'available' => $v['available'] ?? true, 'selected' => ($v['selected'] ?? false) ?: null,
        ], fn ($x) => $x !== null))->values()->all();
        $offers = collect((array) ($d?->offers ?? []))->take(6)->map(fn ($o) => [
            'type' => $o['type'] ?? 'other', 'title' => $o['title'] ?? '', 'inOurPrice' => (bool) ($o['appliesToOurPrice'] ?? false),
        ])->values()->all();

        return [
            'result' => $this->forModel($card) + [
                'mrpBdt' => $card['mrpBdt'],
                'bullets' => collect((array) ($d?->bullets ?? []))->take(6)->map(fn ($b) => mb_substr((string) $b, 0, 220))->values()->all(),
                'specs' => array_slice($this->specs($p), 0, 14, true),
                'variants' => $variants,
                'offers' => $offers,
                'weight' => $d?->weight,
                'delivery' => '7-12 days to Bangladesh',
            ],
            'events' => [['products', ['items' => [$card]]]],
        ];
    }

    private function compare(array $in): array
    {
        $ids = array_slice(array_values(array_unique(array_filter(array_map('strval', (array) ($in['ids'] ?? []))))), 0, 4);
        $products = Product::query()->with('details')->whereIn('id', $ids)->where('hidden', false)->get()->sortBy(fn ($p) => array_search($p->id, $ids))->values();
        if ($products->count() < 2) {
            return $this->fail('need_two_products');
        }
        $cards = $products->map(fn (Product $p) => $this->card($p))->all();
        $specs = $products->map(fn (Product $p) => $this->specs($p))->all();
        // Spec keys most products share, up to 8.
        $keys = collect($specs)->flatMap(fn ($s) => array_keys($s))->countBy()->filter(fn ($n) => $n >= 2)->sortDesc()->keys()->take(8)->all();
        $rows = [
            ['label' => 'Price', 'values' => array_map(fn ($c) => '৳'.number_format($c['priceBdt']), $cards)],
            ['label' => 'Rating', 'values' => array_map(fn ($c) => $c['rating'] ? $c['rating'].' ('.number_format($c['reviews']).')' : 'New', $cards)],
            ['label' => 'Brand', 'values' => array_map(fn ($c) => $c['brand'] ?: '—', $cards)],
            ['label' => 'Store', 'values' => array_map(fn ($c) => self::MARKETS[$c['market']] ?? $c['market'], $cards)],
            ['label' => 'In stock', 'values' => array_map(fn ($c) => $c['inStock'] ? 'Yes' : 'No', $cards)],
        ];
        foreach ($keys as $k) {
            $rows[] = ['label' => (string) $k, 'values' => array_map(fn ($s) => mb_substr((string) ($s[$k] ?? '—'), 0, 60), $specs)];
        }

        return [
            'result' => ['products' => array_map(fn ($c) => $this->forModel($c), $cards), 'rows' => $rows],
            'events' => [['compare', ['items' => $cards, 'rows' => $rows]]],
        ];
    }

    private function history(array $in): array
    {
        $p = $this->find($in['id'] ?? null);
        if (! $p) {
            return $this->fail('not_found');
        }
        $rows = PriceHistory::query()->where('product_id', $p->id)->where('recorded_at', '>=', now()->subDays(90))
            ->orderBy('recorded_at')->get(['price_inr', 'recorded_at']);
        $points = $rows->map(fn ($r) => ['date' => Carbon::parse($r->recorded_at)->toDateString(), 'bdt' => $this->pricing->priceBdt((float) $r->price_inr, (string) $p->category)])
            ->unique('date')->values();
        $now = $this->pricing->productBdt($p);
        if ($points->isEmpty()) {
            return ['result' => ['id' => $p->id, 'currentBdt' => $now, 'points' => 0, 'note' => 'No price history recorded yet'], 'events' => []];
        }
        $bdt = $points->pluck('bdt');

        return [
            'result' => ['id' => $p->id, 'currentBdt' => $now, 'lowestBdt' => $bdt->min(), 'highestBdt' => $bdt->max(), 'averageBdt' => (int) round($bdt->avg()),
                'since' => $points->first()['date'], 'points' => $points->count()],
            'events' => [['priceHistory', ['productId' => $p->id, 'currentBdt' => $now, 'points' => $points->take(-60)->values()->all()]]],
        ];
    }

    private function delivery(array $in): array
    {
        $today = now()->startOfDay();
        $min = 7;
        $max = 12;
        $cutoffs = [];
        foreach (AssistantSettings::holidays() as $h) {
            try {
                $date = Carbon::parse($h['date'])->startOfDay();
            } catch (Throwable) {
                continue;
            }
            if ($date->lt($today) || $date->gt($today->copy()->addDays(120))) {
                continue;
            }
            $orderBy = $date->copy()->subDays($max);
            $status = $today->lte($orderBy) ? 'on_time' : ($today->lte($date->copy()->subDays($min)) ? 'maybe' : 'too_late');
            $cutoffs[] = array_filter(['name' => $h['name'], 'date' => $date->toDateString(), 'orderBy' => $orderBy->toDateString(), 'status' => $status, 'note' => $h['note'] ?? null]);
        }
        $data = [
            'minDays' => $min, 'maxDays' => $max,
            'earliest' => $today->copy()->addDays($min)->toDateString(), 'latest' => $today->copy()->addDays($max)->toDateString(),
            'cutoffs' => $cutoffs,
            'note' => 'Bought in India, shipped via the Skydrop Kolkata hub, customs cleared, delivered to the door. Prices include all import charges.',
        ];
        if (! empty($in['productId']) && ($p = $this->find($in['productId']))) {
            $data['productId'] = $p->id;
            $data['title'] = $p->title;
            $data['restriction'] = app(RestrictionService::class)->forProduct($p);
            $data['inStock'] = (bool) $p->in_stock;
        }

        return ['result' => $data, 'events' => [['delivery', $data]]];
    }

    private function orders(AssistantContext $ctx): array
    {
        if ($ctx->guest()) {
            return $this->loginRequired();
        }
        $orders = Order::query()->where('user_id', $ctx->user->id)->with('items')->latest()->limit(5)->get();
        $items = $orders->map(fn (Order $o) => $this->orderCard($o))->all();

        return ['result' => ['orders' => array_map(fn ($o) => array_diff_key($o, ['items' => 1, 'timeline' => 1]), $items)], 'events' => $items ? [['orders', ['items' => $items]]] : []];
    }

    private function track(array $in, AssistantContext $ctx): array
    {
        if ($ctx->guest()) {
            return $this->loginRequired();
        }
        $ref = strtoupper(trim((string) ($in['id'] ?? '')));
        if ($ref !== '' && ! str_starts_with($ref, 'SR-') && ! str_starts_with($ref, 'SD') && preg_match('/^[A-Z0-9]{6}$/', $ref)) {
            $ref = 'SR-'.$ref;
        }
        // Only this shopper's orders: another user's id behaves exactly like an unknown id.
        $o = Order::query()->where('user_id', $ctx->user->id)
            ->where(fn ($q) => $q->where('id', $ref)->orWhere('tracking_code', $ref))->with(['items', 'events'])->first();
        if (! $o) {
            return $this->fail('order_not_found');
        }
        $card = $this->orderCard($o, true);

        return ['result' => $card, 'events' => [['order', $card]]];
    }

    private function cart(array $in): array
    {
        $p = $this->find($in['productId'] ?? null);
        if (! $p) {
            return $this->fail('not_found');
        }
        $card = $this->card($p);
        if (($card['restriction']['action'] ?? null) === 'block') {
            return $this->fail('cannot_ship_to_bangladesh');
        }
        if (! $card['inStock']) {
            return $this->fail('out_of_stock');
        }
        $data = ['product' => $card, 'variant' => isset($in['variant']) ? mb_substr((string) $in['variant'], 0, 80) : null, 'qty' => max(1, min(10, (int) ($in['qty'] ?? 1)))];

        return ['result' => ['shown' => true, 'note' => 'An Add to cart button is shown; the shopper must tap it.'], 'events' => [['cart', $data]]];
    }

    private function request(array $in, AssistantContext $ctx): array
    {
        if ($ctx->guest()) {
            return $this->loginRequired();
        }
        if (! $this->productRequestsAvailable()) {
            return $this->fail('not_available');
        }
        $cols = array_flip(Schema::getColumnListing('product_requests'));
        $title = mb_substr(trim((string) ($in['title'] ?? '')), 0, 200);
        $note = mb_substr(Pii::scrub(trim((string) ($in['note'] ?? ''))), 0, 500);
        $url = mb_substr(trim((string) ($in['url'] ?? '')), 0, 500);
        $row = array_intersect_key([
            'user_id' => $ctx->user->id, 'title' => $title, 'query' => $title, 'name' => $title, 'url' => $url ?: null,
            'note' => $note ?: null, 'notes' => $note ?: null, 'details' => $note ?: null, 'source' => 'assistant', 'status' => 'new',
            'created_at' => now(), 'updated_at' => now(),
        ], $cols);
        $id = DB::table('product_requests')->insertGetId($row);

        return ['result' => ['ok' => true, 'requestId' => $id], 'events' => [['request', ['id' => $id, 'title' => $title]]]];
    }

    // ---------------------------------------------------------------- helpers

    private function find(mixed $id): ?Product
    {
        $id = trim((string) $id);

        return $id === '' ? null : Product::query()->with('details')->where('hidden', false)->find($id);
    }

    /** Card JSON streamed to clients (subset of the web Product type plus priceBdt/mrpBdt). */
    public function card(Product $p): array
    {
        $price = $this->pricing->productBdt($p);
        $mrp = $this->pricing->priceBdt((float) ($p->mrp_inr ?: $p->price_inr), (string) $p->category);

        return [
            'id' => $p->id, 'market' => $p->market, 'category' => $p->category, 'title' => (string) $p->title, 'brand' => (string) $p->brand,
            'image' => $p->image, 'placeholder' => $p->placeholder, 'priceBdt' => $price, 'mrpBdt' => max($mrp, $price),
            'rating' => round((float) $p->rating, 1), 'reviews' => (int) $p->reviews, 'inStock' => (bool) $p->in_stock,
            'weightKg' => $this->pricing->rule((string) $p->category)['weight'],
            'restriction' => app(RestrictionService::class)->forProduct($p),
        ];
    }

    /** What the model sees: compact, prices only from us. */
    private function forModel(array $c): array
    {
        return array_filter([
            'id' => $c['id'], 'title' => mb_substr($c['title'], 0, 140), 'brand' => $c['brand'] ?: null, 'store' => self::MARKETS[$c['market']] ?? $c['market'],
            'priceBdt' => $c['priceBdt'], 'discountPct' => $c['mrpBdt'] > $c['priceBdt'] ? (int) round(100 - $c['priceBdt'] / $c['mrpBdt'] * 100) : null,
            'rating' => $c['rating'] ?: null, 'reviews' => $c['reviews'] ?: null, 'inStock' => $c['inStock'],
            'restriction' => $c['restriction'] ? $c['restriction']['action'].': '.$c['restriction']['message'] : null,
        ], fn ($v) => $v !== null);
    }

    /** @return array<string, string> */
    private function specs(Product $p): array
    {
        $out = [];
        foreach ((array) ($p->details?->specs ?? []) as $k => $v) {
            if (is_array($v)) { // [{ name/key, value }] shape
                $k = $v['name'] ?? $v['key'] ?? $v['k'] ?? $k;
                $v = $v['value'] ?? $v['v'] ?? '';
            }
            if (is_string($k) && is_scalar($v) && trim((string) $v) !== '') {
                $out[trim($k)] = trim((string) $v);
            }
        }

        return $out;
    }

    private function orderCard(Order $o, bool $timeline = false): array
    {
        $card = [
            'id' => $o->id, 'status' => $o->status, 'createdAt' => $o->created_at?->toIso8601String(), 'total' => (int) round((float) $o->total_bdt),
            'paymentStatus' => $o->payment_status, 'trackingCode' => (string) $o->tracking_code, 'itemCount' => (int) $o->items->sum('qty'),
            'items' => $o->items->take(4)->map(fn ($i) => ['title' => $i->title, 'image' => $i->image, 'qty' => (int) $i->qty])->values()->all(),
        ];
        if ($o->created_at && ! in_array($o->status, ['delivered', 'cancelled'], true)) {
            $card['expectedBy'] = $o->created_at->copy()->addDays(12)->toDateString();
        }
        if ($timeline) {
            $card['timeline'] = $o->events->map(fn ($e) => ['status' => $e->status, 'at' => $e->at?->toIso8601String()])->values()->all();
        }

        return $card;
    }

    private function loginRequired(): array
    {
        return ['result' => ['error' => 'login_required', 'message' => 'The shopper must sign in to see orders.'], 'events' => [['login', ['reason' => 'orders']]]];
    }

    private function fail(string $code): array
    {
        return ['result' => ['error' => $code], 'events' => []];
    }
}

<?php

namespace App\Services\Catalog;

use App\Jobs\Catalog\FetchProductJob;
use App\Jobs\Catalog\FetchSearchJob;
use App\Jobs\Catalog\FetchVariantsJob;
use App\Models\Category;
use App\Models\Marketplace;
use App\Models\Product;
use App\Models\SearchCache;
use App\Services\PricingService;
use App\Sources\Exceptions\ProductGoneException;
use App\Sources\Support\ApiLogger;
use App\Sources\Support\SourceProduct;
use Illuminate\Contracts\Cache\LockTimeoutException;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Catalog orchestration: cache-first reads with stale-while-revalidate, queued refreshes with single-flight,
 * variant fetching, deal ingestion and live checkout verification. Marketplaces with live_fetch=false never
 * reach a provider and are served from the local catalog only.
 */
class CatalogService
{
    /** Store result pages are ~48 items; our pages are 24. */
    private const STORE_PAGE_SIZE = 48;

    public function __construct(
        private CatalogQuery $query,
        private ProviderChain $chain,
        private ProductWriter $writer,
        private JobTracker $jobs,
        private MarketResolver $markets,
        private PricingService $pricing,
        private ApiLogger $logger,
    ) {}

    public function isLive(string $market): bool
    {
        return (bool) Cache::remember("catalog:live:$market", 30, fn () => (bool) Marketplace::query()
            ->whereKey($market)->where('enabled', true)->where('live_fetch', true)->exists());
    }

    // ---------------------------------------------------------------- search

    /**
     * @param  array<string, mixed>  $sp  q, store, category, sort, min, max, rating, fast, page
     * @return array{status: string, jobId?: string, total: int, page: int, pages: int, items: Collection<int, Product>}
     */
    public function search(array $sp, bool $crawler = false): array
    {
        $words = CatalogQuery::words($sp['q'] ?? null);
        $status = 'ready';
        $jobId = null;
        $boost = [];

        if ($words) {
            $q = implode(' ', $words);
            $sort = $this->storeSort((string) ($sp['sort'] ?? 'relevance'));
            $storePages = (int) ceil(max(1, (int) ($sp['page'] ?? 1)) * CatalogQuery::PER_PAGE / self::STORE_PAGE_SIZE);
            foreach ($this->query->markets($sp['store'] ?? null) as $market) {
                if (! $this->isLive($market)) {
                    continue;
                }
                for ($page = 1; $page <= $storePages; $page++) {
                    $row = SearchCache::query()->where('cache_key', $this->searchKey($market, $q, $page, $sort))->first();
                    $age = $row ? now()->diffInMinutes($row->fetched_at, true) : null;
                    if ($row && $age <= RetailSettings::ttl('search_stale')) {
                        foreach ((array) json_decode($row->product_ids, true) as $i => $id) {
                            $boost[$id] ??= ($page - 1) * self::STORE_PAGE_SIZE + $i;
                        }
                        $this->logger->cacheHit('search', $market, "$q#$page");
                    }
                    if ($crawler || ($row && $age <= RetailSettings::ttl('search_fresh'))) {
                        continue;
                    }
                    $id = $this->queueSearch($market, $q, $page, $sort, $sp['category'] ?? null);
                    if (! $row) {
                        $status = 'pending';
                        $jobId ??= $id;
                    }
                }
            }
        }

        $res = $this->query->search($sp, $boost);

        return ['status' => $status] + ($jobId ? ['jobId' => $jobId] : []) + $res;
    }

    public function queueSearch(string $market, string $q, int $page, string $sort, ?string $category = null): string
    {
        return $this->jobs->dispatch('search', $this->searchKey($market, $q, $page, $sort),
            fn (string $jid) => new FetchSearchJob($market, $q, $page, $sort, $category, $jid));
    }

    /** Fetch one store result page, upsert every listing (free refresh) and cache the id list. */
    public function refreshSearch(string $market, string $q, int $page = 1, string $sort = 'relevance', ?string $category = null, string $purpose = 'browse'): ?SearchCache
    {
        $key = $this->searchKey($market, $q, $page, $sort);
        $lock = Cache::lock("catalog:search:$key", 120);
        if (! $lock->get()) {
            return SearchCache::query()->where('cache_key', $key)->first();
        }
        try {
            $result = $this->chain->search($market, $q, $page, $sort, $category, $purpose);
            if (! $result) {
                return SearchCache::query()->where('cache_key', $key)->first(); // all providers down: stale cache
            }
            $ids = [];
            foreach ($result->items as $row) {
                if ($p = $this->writer->listing($row, $result->provider, $result->pincodeVerified, $category)) {
                    $ids[] = $p->id;
                }
            }
            $cache = SearchCache::query()->updateOrCreate(['cache_key' => $key], [
                'market' => $market, 'query' => mb_substr($q, 0, 250), 'page' => $page, 'sort' => $sort,
                'product_ids' => json_encode($ids), 'total' => $result->total, 'source' => $result->provider, 'fetched_at' => now(),
            ]);
            $this->prefetch($ids);

            return $cache;
        } finally {
            $lock->release();
        }
    }

    // ---------------------------------------------------------------- products

    /** @return array{status: string, jobId?: string, product: Product}|null */
    public function product(string $id, bool $crawler = false): ?array
    {
        $p = Product::query()->with('details')->find($id);
        if (! $p) {
            $market = $this->markets->forId($id);
            if (! $market || $crawler || ! $this->isLive($market)) {
                return null;
            }
            $p = $this->refreshProduct($id, interactive: true);
            if (! $p) {
                return null;
            }
            $this->queueVariants($p->id);

            return ['status' => 'ready', 'product' => $p->load('details')];
        }

        Product::query()->whereKey($id)->increment('views');
        if ($crawler || $p->demo || ! $this->isLive($p->market)) {
            return ['status' => 'ready', 'product' => $p];
        }
        if ($this->isFresh($p)) {
            $this->logger->cacheHit('product', $p->market, $p->id);

            return ['status' => 'ready', 'product' => $p];
        }

        return ['status' => 'pending', 'jobId' => $this->queueProduct($p->id), 'product' => $p];
    }

    /** Detail freshness: 24 h normally, 30 min while a deal ends within a day, and stale as soon as a deal expired. */
    public function isFresh(Product $p, ?int $ttlMinutes = null): bool
    {
        if (! $p->detailed || ! $p->details_fetched_at) {
            return false;
        }
        if ($p->deal_ends_at && $p->deal_ends_at->isPast()) {
            return false;
        }
        $ttl = $p->deal_ends_at && $p->deal_ends_at->lt(now()->addDay())
            ? RetailSettings::ttl('lightning')
            : ($ttlMinutes ?? RetailSettings::ttl('product_fresh'));

        return $p->details_fetched_at->gt(now()->subMinutes($ttl));
    }

    public function queueProduct(string $id): string
    {
        return $this->jobs->dispatch('product', "product:$id", fn (string $jid) => new FetchProductJob($id, $jid));
    }

    /** Always calls a provider (no cache): used by jobs, admin refresh and the debug command. */
    public function refreshProduct(string $id, bool $interactive = false, string $purpose = 'browse'): ?Product
    {
        return $this->fetch($id, $interactive, $purpose)[0];
    }

    /**
     * @return array{0: ?Product, 1: bool} the product row and whether a provider actually answered
     */
    private function fetch(string $id, bool $interactive, string $purpose): array
    {
        $market = $this->markets->forId($id);
        if (! $market) {
            return [null, false];
        }
        $started = now();
        $lock = Cache::lock("catalog:product:$id", 90);
        try {
            $interactive ? $lock->block(40) : ($lock->get() ?: throw new LockTimeoutException);
        } catch (LockTimeoutException) {
            // Someone else is fetching it: use their result if it landed meanwhile.
            $p = Product::query()->find($id);

            return [$p, (bool) ($p?->price_checked_at?->gte($started))];
        }
        try {
            $sp = $this->chain->product($market, $id, $purpose, $interactive);
            if (! $sp instanceof SourceProduct) {
                return [Product::query()->find($id), false];
            }
            $existing = Product::query()->find($id);

            return [$this->writer->detailed($sp, $existing?->category), true];
        } catch (ProductGoneException) {
            $this->writer->gone($id);

            return [Product::query()->find($id), true];
        } finally {
            $lock->release();
        }
    }

    // ---------------------------------------------------------------- variants

    public function queueVariants(string $id): ?string
    {
        if (RetailSettings::get('variants.mode') !== 'eager') {
            return null;
        }
        $p = Product::query()->with('details')->find($id);
        if (! $p || ! $this->isLive($p->market) || count($p->details?->variants ?? []) < 2) {
            return null;
        }
        Cache::forget("catalog:cancel-variants:$id");

        return $this->jobs->dispatch('variants', "variants:$id", fn (string $jid) => new FetchVariantsJob($id, 'colours', $jid));
    }

    /** Shopper bounced: drop the queued size-variant phase for this product. */
    public function cancelVariants(string $id): bool
    {
        Cache::put("catalog:cancel-variants:$id", true, now()->addMinutes(15));

        return $this->jobs->active('variants', "variants:$id") !== null;
    }

    public function variantsCancelled(string $id): bool
    {
        return (bool) Cache::get("catalog:cancel-variants:$id");
    }

    /**
     * Split sibling ids into the colour phase (one per colour/style) and the size phase (the rest),
     * skipping siblings fetched within the sibling TTL, capped at variants.max.
     *
     * @return array{colours: list<string>, sizes: list<string>}
     */
    public function variantPlan(Product $p): array
    {
        $variants = collect($p->details?->variants ?? [])->filter(fn ($v) => ! empty($v['id']) && $v['id'] !== $p->id);
        $colourDim = collect($p->details?->variants[0]['dimensions'] ?? [])->keys()
            ->first(fn ($k) => (bool) preg_match('/colou?r|style|pattern|flavou?r|design/i', (string) $k));
        $selected = collect($p->details?->variants ?? [])->firstWhere('selected', true)['dimensions'] ?? [];

        $colours = [];
        if ($colourDim) {
            foreach ($variants->groupBy(fn ($v) => $v['dimensions'][$colourDim] ?? '') as $colour => $group) {
                if ($colour === ($selected[$colourDim] ?? null)) {
                    continue; // the landing product already shows this colour
                }
                // Prefer the sibling that matches the shopper's current size.
                $pick = $group->first(fn ($v) => array_diff_assoc(array_diff_key($v['dimensions'], [$colourDim => 1]), $selected) === []) ?? $group->first();
                $colours[] = $pick['id'];
            }
        }
        $sizes = $variants->pluck('id')->diff($colours)->values()->all();

        $fresh = Product::query()->whereIn('id', [...$colours, ...$sizes])->get()
            ->filter(fn (Product $s) => $this->isFresh($s, RetailSettings::ttl('sibling_fresh')))->pluck('id')->all();
        $max = RetailSettings::int('variants.max', 60);
        $colours = array_slice(array_values(array_diff($colours, $fresh)), 0, $max);
        $sizes = array_slice(array_values(array_diff($sizes, $fresh)), 0, max(0, $max - count($colours)));

        return ['colours' => $colours, 'sizes' => $sizes];
    }

    /**
     * Fetch siblings concurrently and store them in the parent's category.
     *
     * @param  list<string>  $ids
     */
    public function fetchSiblings(Product $parent, array $ids): int
    {
        $n = 0;
        foreach (array_chunk($ids, 10) as $chunk) {
            foreach ($this->chain->products($parent->market, $chunk) as $id => $sp) {
                if ($sp instanceof SourceProduct) {
                    $this->writer->detailed($sp, $parent->category);
                    $n++;
                } elseif ($sp === false) {
                    $this->writer->gone((string) $id);
                }
            }
        }

        return $n;
    }

    // ---------------------------------------------------------------- seeding and deals

    public function seedBestsellers(string $market): int
    {
        if (! $this->isLive($market)) {
            return 0;
        }
        $n = 0;
        foreach (Category::query()->where('market', $market)->where('active', true)->get() as $cat) {
            for ($page = 1; $page <= max(1, (int) $cat->seed_pages); $page++) {
                $res = $this->chain->bestsellers($market, (string) ($cat->browse_node ?: $cat->name), $page);
                foreach ($res?->items ?? [] as $row) {
                    $n += $this->writer->listing($row, $res->provider, $res->pincodeVerified, $cat->slug) ? 1 : 0;
                }
                if (! $res || $page >= $res->pages) {
                    break;
                }
            }
        }

        return $n;
    }

    /** Pull the store's deals page and fetch details (with offers) for the top deals, within budget. */
    public function ingestDeals(string $market): int
    {
        if (! $this->isLive($market) || ! app(BudgetGuard::class)->allowsBackground()) {
            return 0;
        }
        $page = $this->chain->deals($market);
        if (! $page) {
            return 0;
        }
        $ids = [];
        foreach ($page->items as $row) {
            if (! empty($row['stub'])) {
                $ids[] = $row['id'];
            } elseif ($p = $this->writer->listing($row, $page->provider, $page->pincodeVerified)) {
                $ids[] = $p->id;
            }
        }
        $ids = array_slice(array_values(array_unique($ids)), 0, RetailSettings::int('offers.ingest_max_products', 40));
        $stale = array_values(array_filter($ids, fn ($id) => ! (($p = Product::query()->find($id)) && $this->isFresh($p, RetailSettings::ttl('lightning')))));
        $n = 0;
        foreach (array_chunk($stale, 10) as $chunk) {
            foreach ($this->chain->products($market, $chunk) as $id => $sp) {
                if ($sp instanceof SourceProduct) {
                    $this->writer->detailed($sp, Product::query()->whereKey($id)->value('category'));
                    $n++;
                }
            }
        }

        return $n;
    }

    // ---------------------------------------------------------------- checkout

    /**
     * Fresh provider check per item (never cached) with the Kolkata pincode. With live_fetch=false the stored
     * catalog row is the source of truth. Result shape follows docs/API.md POST /checkout/verify, plus
     * offerExpired when a coupon or deal used for the shopper's price is gone.
     *
     * @param  array<int, array<string, mixed>>  $items  [{productId, variant?, qty, unitBdt}]
     * @return array{ok: bool, checkedAt: string, results: list<array<string, mixed>>}
     */
    public function verify(array $items): array
    {
        $results = [];
        foreach ($items as $item) {
            $id = (string) ($item['productId'] ?? $item['product_id'] ?? '');
            $unit = (int) round((float) ($item['unitBdt'] ?? $item['unit_bdt'] ?? 0));
            $results[] = $this->verifyOne($id, $unit);
        }

        return ['ok' => collect($results)->every(fn ($r) => $r['ok']), 'checkedAt' => now()->toIso8601String(), 'results' => $results];
    }

    /** @return array<string, mixed> */
    private function verifyOne(string $id, int $unitBdt): array
    {
        $before = Product::query()->find($id);
        if (! $before) {
            return ['productId' => $id, 'ok' => false, 'freshBdt' => null, 'changed' => false, 'deliverable' => false, 'offerExpired' => false, 'reason' => 'not_found', 'provider' => null];
        }
        $hadCoupon = (float) $before->coupon_inr > 0;
        $hadDeal = $before->deal_badge !== null && ! ($before->deal_ends_at?->isPast() ?? false);
        $provider = 'catalog';
        $p = $before;

        if ($this->isLive($before->market) && ! $before->demo) {
            [$fresh, $answered] = $this->fetch($id, true, 'verify');
            if (! $answered || ! $fresh) {
                return ['productId' => $id, 'ok' => false, 'freshBdt' => $this->pricing->productBdt($before), 'changed' => false,
                    'deliverable' => ! $before->hidden, 'offerExpired' => false, 'reason' => 'verify_failed', 'provider' => null];
            }
            $p = $fresh;
            $provider = $p->source;
        }

        $dealGone = $hadDeal && ($p->deal_badge === null || ($p->deal_ends_at?->isPast() ?? false));
        $offerExpired = ($hadCoupon && ! ((float) $p->coupon_inr > 0)) || $dealGone
            || ($p->deal_ends_at !== null && $p->deal_ends_at->isPast() && (float) $p->effective_price_inr > 0);
        $freshBdt = $this->pricing->productBdt($p);
        $deliverable = ! $p->hidden;
        $ok = $p->in_stock && $deliverable && $p->price_inr > 0;

        return array_filter([
            'productId' => $id,
            'ok' => $ok,
            'freshBdt' => $freshBdt,
            'changed' => $unitBdt > 0 && $freshBdt !== $unitBdt,
            'deliverable' => $deliverable,
            'offerExpired' => $offerExpired,
            'pincodeVerified' => (bool) $p->pincode_verified,
            'reason' => $ok ? null : (! $p->in_stock ? 'out_of_stock' : 'undeliverable'),
            'provider' => $provider,
        ], fn ($v) => $v !== null);
    }

    // ---------------------------------------------------------------- helpers

    /** Queue detail fetches for the first few results of a fresh search (they are the likely clicks). */
    private function prefetch(array $ids): void
    {
        $n = RetailSettings::int('prefetch_top', 3);
        foreach (Product::query()->whereIn('id', array_slice($ids, 0, $n))->get() as $p) {
            if (! $this->isFresh($p)) {
                $this->queueProduct($p->id);
            }
        }
    }

    private function storeSort(string $sort): string
    {
        return in_array($sort, ['price_asc', 'price_desc', 'rating'], true) ? $sort : 'relevance';
    }

    private function searchKey(string $market, string $q, int $page, string $sort): string
    {
        return "$market|$q|$page|$sort";
    }
}

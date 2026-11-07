<?php

namespace App\Services\Catalog;

use App\Sources\Exceptions\ProductGoneException;
use App\Sources\MarketplaceSource;
use App\Sources\Support\SearchPage;
use App\Sources\Support\SourceProduct;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Tries a marketplace's providers in order (Oxylabs -> Omkar -> OpenWebNinja for Amazon) behind circuit breakers
 * and the budget guard. Returns null when every provider failed, so the caller serves stale cache.
 * Records from providers without pincode support come back with pincodeVerified=false.
 */
class ProviderChain
{
    public function __construct(private CircuitBreaker $breaker, private BudgetGuard $budget) {}

    /** @return list<MarketplaceSource> */
    public function sources(string $market): array
    {
        return array_values(array_filter(
            array_map(fn (string $class) => app($class), (array) config("retail.sources.$market", [])),
            fn (MarketplaceSource $s) => $s->isConfigured(),
        ));
    }

    /** @param 'browse'|'verify'|'background' $purpose */
    public function product(string $market, string $id, string $purpose = 'browse', bool $interactive = false): ?SourceProduct
    {
        return $this->attempt($market, $purpose, fn (MarketplaceSource $s) => ($interactive ? $s->withHedging() : $s)->product($id));
    }

    public function search(string $market, string $query, int $page, string $sort, ?string $category = null, string $purpose = 'browse'): ?SearchPage
    {
        return $this->attempt($market, $purpose, fn (MarketplaceSource $s) => $s->search($query, $page, $sort, $category));
    }

    public function bestsellers(string $market, string $categoryId, int $page = 1): ?SearchPage
    {
        return $this->attempt($market, 'background', fn (MarketplaceSource $s) => $s->bestsellers($categoryId, $page));
    }

    public function deals(string $market): ?SearchPage
    {
        return $this->attempt($market, 'background', fn (MarketplaceSource $s) => $s->deals(), onlyMetered: true);
    }

    /**
     * Concurrent product fetches from the first usable provider; failures are retried one by one down the chain.
     *
     * @param  list<string>  $ids
     * @return array<string, SourceProduct|false|null> false = the store says the product is gone
     */
    public function products(string $market, array $ids, string $purpose = 'background'): array
    {
        $out = array_fill_keys($ids, null);
        $first = $this->usable($market, $purpose)[0] ?? null;
        if ($first && $ids) {
            foreach ($first->products($ids) as $id => $r) {
                if ($r instanceof SourceProduct) {
                    $out[$id] = $r;
                    $this->breaker->success($this->key($first));
                } elseif ($r instanceof ProductGoneException) {
                    $out[$id] = false;
                } else {
                    $this->breaker->failure($this->key($first));
                }
            }
        }
        foreach ($out as $id => $r) {
            if ($r === null) {
                try {
                    $out[$id] = $this->product($market, (string) $id, $purpose);
                } catch (ProductGoneException) {
                    $out[$id] = false;
                }
            }
        }

        return $out;
    }

    /** @return list<MarketplaceSource> */
    private function usable(string $market, string $purpose, bool $onlyMetered = false): array
    {
        $out = [];
        if (! $this->budget->allowsStore($market, $purpose)) {
            return []; // System → Limits: per-store daily live-fetch cap reached → cached data only
        }
        foreach ($this->sources($market) as $s) {
            if ($onlyMetered && ! $s->isMetered()) {
                continue;
            }
            if ($s->isMetered() && ! ($purpose === 'background' ? $this->budget->allowsBackground() : $this->budget->allowsMetered($purpose))) {
                continue;
            }
            if (! $this->breaker->allows($this->key($s))) {
                continue;
            }
            $out[] = $s;
        }

        return $out;
    }

    /**
     * @template T
     *
     * @param  Closure(MarketplaceSource): T  $call
     * @return T|null
     */
    private function attempt(string $market, string $purpose, Closure $call, bool $onlyMetered = false): mixed
    {
        foreach ($this->usable($market, $purpose, $onlyMetered) as $source) {
            try {
                $result = $call($source);
                $this->breaker->success($this->key($source));

                return $result;
            } catch (ProductGoneException $e) {
                $this->breaker->success($this->key($source));
                throw $e;
            } catch (Throwable $e) {
                $this->breaker->failure($this->key($source));
                Log::warning("catalog: {$source->name()} failed for $market: {$e->getMessage()}");
            }
        }

        return null;
    }

    private function key(MarketplaceSource $s): string
    {
        return $s->name().':'.$s->market();
    }
}

<?php

namespace App\Sources;

use App\Sources\Support\SearchPage;
use Throwable;

/** Shared defaults: sequential multi-fetch, hedging flag, no deals page. */
abstract class AbstractSource implements MarketplaceSource
{
    protected bool $hedge = false;

    public function withHedging(bool $on = true): static
    {
        $copy = clone $this;
        $copy->hedge = $on;

        return $copy;
    }

    public function isMetered(): bool
    {
        return false;
    }

    public function products(array $ids): array
    {
        $out = [];
        foreach ($ids as $id) {
            try {
                $out[$id] = $this->product($id);
            } catch (Throwable $e) {
                $out[$id] = $e;
            }
        }

        return $out;
    }

    public function deals(int $page = 1): SearchPage
    {
        return new SearchPage([]);
    }
}

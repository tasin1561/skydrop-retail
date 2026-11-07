<?php

namespace App\Providers;

use App\Jobs\Commerce\CheckProductWatches;
use App\Models\Product;
use App\Models\ProductWatch;
use App\Services\Commerce\Verify\CatalogPriceVerifier;
use App\Services\Commerce\Verify\PriceVerifier;
use Illuminate\Support\ServiceProvider;

// Commerce module wiring (kept separate from AppServiceProvider, which the catalog module may also edit).
class CommerceServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PriceVerifier::class, CatalogPriceVerifier::class);
    }

    public function boot(): void
    {
        // Price-drop / back-in-stock watches: check when a watched product's price or stock changes.
        Product::updated(function (Product $p) {
            if ($p->wasChanged(['price_inr', 'in_stock', 'hidden']) && ProductWatch::query()->where('product_id', $p->id)->where('active', true)->exists()) {
                CheckProductWatches::dispatch([$p->id])->afterCommit();
            }
        });
    }
}

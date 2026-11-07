<?php

namespace App\Providers;

use App\Models\Product;
use App\Models\ProductDetail;
use App\Observers\ProductCategoryObserver;
use App\Observers\ProductDetailCategoryObserver;
use App\Services\Categories\CategoryMatcher;
use App\Services\Categories\CategoryTree;
use Illuminate\Support\ServiceProvider;

/** Unified category tree: singletons (per-request memo of the cached tree) and product categorisation observers. */
class CategoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(CategoryTree::class);
        $this->app->singleton(CategoryMatcher::class);
    }

    public function boot(): void
    {
        Product::observe(ProductCategoryObserver::class);
        ProductDetail::observe(ProductDetailCategoryObserver::class);
    }
}

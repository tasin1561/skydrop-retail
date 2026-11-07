<?php

namespace App\Jobs\Commerce;

use App\Models\Product;
use App\Models\ProductWatch;
use App\Services\Commerce\Notifier;
use App\Services\PricingService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Notifies watchers of a product (price drop below target/baseline, back in stock). Dispatched when a product is
 * updated (Product "updated" listener in AppServiceProvider) and hourly for all active watches.
 */
class CheckProductWatches implements ShouldQueue
{
    use Queueable;

    /** @param array<int, string>|null $productIds null = every product with an active watch */
    public function __construct(public ?array $productIds = null) {}

    public function handle(PricingService $pricing, Notifier $notifier): void
    {
        $q = ProductWatch::query()->with('user')->where('active', true);
        if ($this->productIds !== null) {
            $q->whereIn('product_id', $this->productIds);
        }
        $watches = $q->get();
        if ($watches->isEmpty()) {
            return;
        }
        $products = Product::query()->whereIn('id', $watches->pluck('product_id')->unique())->get()->keyBy('id');

        foreach ($watches as $w) {
            $p = $products[$w->product_id] ?? null;
            if (! $p || ! $w->user) {
                continue;
            }
            $available = $p->in_stock && ! $p->hidden;
            $price = $pricing->priceBdt((float) $p->price_inr, (string) $p->category);

            if ($w->type === 'back_in_stock' && $available) {
                $notifier->backInStock($w->user, $p->title, $p->id);
                $w->forceFill(['active' => false, 'notified_at' => now()])->save();
            } elseif ($w->type === 'price_drop' && $available) {
                $target = $w->target_bdt ?? ($w->baseline_bdt ? $w->baseline_bdt - 1 : null);
                if ($target !== null && $price <= $target) {
                    $notifier->priceDrop($w->user, $p->title, $price, $p->id);
                    // Keep watching from the new, lower price.
                    $w->forceFill(['notified_at' => now(), 'baseline_bdt' => $price, 'target_bdt' => null])->save();
                }
            }
        }
    }
}

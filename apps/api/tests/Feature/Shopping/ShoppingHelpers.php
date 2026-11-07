<?php

namespace Tests\Feature\Shopping;

use App\Models\Order;
use App\Models\PriceHistory;
use App\Models\Product;
use App\Services\Commerce\Payments\MockGateway;
use App\Support\Features;
use Tests\Feature\Commerce\CommerceHelpers;

trait ShoppingHelpers
{
    use CommerceHelpers;

    protected function setUpShopping(): void
    {
        $this->setUpCommerce();
        Features::flush();
    }

    /** @param array<int, float> $byDaysAgo daysAgo => price INR */
    protected function history(Product $p, array $byDaysAgo): void
    {
        foreach ($byDaysAgo as $daysAgo => $inr) {
            PriceHistory::query()->create(['product_id' => $p->id, 'price_inr' => $inr, 'in_stock' => true, 'recorded_at' => now()->subDays($daysAgo)->setTime(10, 0)]);
        }
    }

    /** Pays the latest pending payment of an order through the mock gateway callback. */
    protected function pay(Order $o): Order
    {
        $pay = $o->payments()->where('status', 'pending')->latest('id')->firstOrFail();
        $this->postJson('/api/v1/webhooks/payments/mock?payment='.$pay->id, ['status' => 'success', 'signature' => MockGateway::signature($pay->id, 'success')])->assertOk();

        return $o->refresh();
    }
}

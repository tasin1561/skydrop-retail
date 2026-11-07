<?php

namespace Tests\Feature\Commerce;

use App\Models\ProductReview;
use App\Services\Commerce\OrderWorkflow;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReviewTest extends TestCase
{
    use CommerceHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCommerce();
    }

    public function test_only_buyers_of_delivered_orders_can_review(): void
    {
        $u = $this->shopper();
        $p = $this->product('A1', 1000);
        $body = ['rating' => 5, 'title' => 'Great', 'body' => 'Arrived in perfect shape.'];

        $this->postJson('/api/v1/products/A1/reviews', $body)->assertForbidden();

        $o = $this->placeOrder($u, [$p]);
        $this->postJson('/api/v1/products/A1/reviews', $body)->assertForbidden(); // not delivered yet

        app(OrderWorkflow::class)->transition($o, 'delivered', 'admin');
        $this->postJson('/api/v1/products/A1/reviews', $body)->assertCreated()
            ->assertJsonPath('review.status', 'pending')->assertJsonPath('review.verifiedPurchase', true);

        // Pending reviews are visible to their author only.
        $this->getJson('/api/v1/products/A1/reviews')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('canReview', true);
        $this->app['auth']->forgetGuards();
        $this->getJson('/api/v1/products/A1/reviews')->assertOk()->assertJsonCount(0, 'items');

        ProductReview::query()->update(['status' => 'approved']);
        $this->getJson('/api/v1/products/A1/reviews')->assertOk()->assertJsonCount(1, 'items')->assertJsonPath('average', 5);
    }
}

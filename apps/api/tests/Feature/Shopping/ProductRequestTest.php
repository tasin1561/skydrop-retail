<?php

namespace Tests\Feature\Shopping;

use App\Models\ProductRequest;
use App\Models\User;
use App\Services\Commerce\Sms\LogSms;
use App\Services\Shopping\ProductRequestService;
use App\Support\Features;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ProductRequestTest extends TestCase
{
    use RefreshDatabase, ShoppingHelpers;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpShopping();
    }

    public function test_guest_request_needs_phone_and_quote_creates_pay_link(): void
    {
        $this->postJson('/api/v1/product-requests', ['description' => 'Yonex badminton racket Astrox 88D'])->assertStatus(422)->assertJsonValidationErrors('phone');
        $this->postJson('/api/v1/product-requests', ['phone' => '01712345678'])->assertStatus(422)->assertJsonValidationErrors(['url', 'description']);

        $r = $this->postJson('/api/v1/product-requests', ['description' => 'Yonex badminton racket Astrox 88D', 'phone' => '01712345678',
            'qty' => 2, 'budgetBdt' => 25000, 'source' => 'search', 'query' => 'astrox 88d'])->assertCreated()
            ->assertJsonPath('request.status', 'new')->assertJsonPath('request.qty', 2);
        $req = ProductRequest::query()->findOrFail($r->json('request.id'));
        $this->assertSame('+8801712345678', $req->phone);
        $this->assertNull($req->user_id);

        // Staff quote: an order with a custom line, SMS with a pay link, guest matched by phone.
        LogSms::$sent = [];
        $order = app(ProductRequestService::class)->quote($req, 11000, 'Original, ships in 10 days', null, 300);
        $this->assertSame(22300.0, (float) $order->total_bdt);
        $this->assertSame('REQ-'.$req->id, $order->items()->first()->product_id);
        $req->refresh();
        $this->assertSame('quoted', $req->status);
        $this->assertNotNull($req->user_id);
        $this->assertStringContainsString('?pay=1', json_encode(LogSms::$sent));

        // The customer signs in and sees it.
        Sanctum::actingAs(User::query()->findOrFail($req->user_id));
        $this->getJson('/api/v1/product-requests')->assertOk()->assertJsonPath('items.0.status', 'quoted')
            ->assertJsonPath('items.0.quoteBdt', 11000)->assertJsonPath('items.0.payUrl', '/orders/'.$order->id.'?pay=1');
        $this->postJson("/api/v1/orders/{$order->id}/pay")->assertOk()->assertJsonPath('payment.amount', 22300);
        $this->pay($order);
        $this->getJson('/api/v1/product-requests')->assertJsonPath('items.0.status', 'ordered')->assertJsonPath('items.0.payUrl', null);
    }

    public function test_signed_in_request_with_link_and_status_flow(): void
    {
        $this->shopper();
        $r = $this->postJson('/api/v1/product-requests', ['url' => 'https://www.amazon.in/dp/B0ABCDEF12', 'source' => 'header'])->assertCreated();
        $req = ProductRequest::query()->findOrFail($r->json('request.id'));
        $this->assertSame('+8801712345678', $req->phone);

        $svc = app(ProductRequestService::class);
        $svc->setStatus($req, 'sourcing', 'Checking sellers');
        $this->assertSame('sourcing', $req->refresh()->status);
        $this->expectExceptionMessage('cannot move');
        $svc->setStatus($req, 'ordered');
    }

    public function test_feature_switch(): void
    {
        Features::set('product_requests', false);
        $this->postJson('/api/v1/product-requests', ['description' => 'x', 'phone' => '01712345678'])->assertNotFound();
    }
}

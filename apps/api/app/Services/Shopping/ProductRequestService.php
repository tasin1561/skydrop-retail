<?php

namespace App\Services\Shopping;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Models\ProductRequest;
use App\Models\User;
use App\Services\Catalog\UrlResolver;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Notifier;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Phone;
use App\Services\Messaging\Messenger;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/** "Request a product": inbox for things we do not list, and staff quotes with a pay link. */
class ProductRequestService
{
    public const FLOW = ['new' => ['sourcing', 'quoted', 'declined'], 'sourcing' => ['quoted', 'declined', 'new'], 'quoted' => ['ordered', 'declined', 'sourcing'],
        'ordered' => [], 'declined' => ['new']];

    public function create(?User $user, array $data): ProductRequest
    {
        $phone = $user?->phone ?? Phone::normalize($data['phone'] ?? null);
        $req = new ProductRequest([
            'user_id' => $user?->id, 'name' => $data['name'] ?? $user?->name, 'phone' => $phone,
            'url' => $data['url'] ?? null, 'description' => $data['description'] ?? null, 'photo_url' => $data['photoUrl'] ?? null,
            'qty' => (int) ($data['qty'] ?? 1), 'budget_bdt' => $data['budgetBdt'] ?? null, 'search_query' => $data['query'] ?? null,
            'source' => $data['source'] ?? 'web', 'status' => 'new',
        ]);
        if ($req->url) {
            $this->resolve($req);
        }
        $req->save();

        return $req;
    }

    /** Links a pasted amazon.in / Flipkart / BigBasket / Myntra URL to the catalog (POST /products/resolve logic), when the catalog module offers it. */
    private function resolve(ProductRequest $req): void
    {
        if (! class_exists(UrlResolver::class)) {
            return;
        }
        rescue(function () use ($req) {
            $resolver = app(UrlResolver::class);
            if (! $resolver->isSupportedHost((string) $req->url)) {
                return;
            }
            $found = $resolver->resolve((string) $req->url);
            if ($found) {
                $req->market = $found['market'] ?? null;
                $req->product_id = $found['id'] ?? null;
            }
        }, null, false);
    }

    public function setStatus(ProductRequest $req, string $status, ?string $note = null): ProductRequest
    {
        if ($status !== $req->status && ! in_array($status, self::FLOW[$req->status] ?? [], true)) {
            throw new CommerceException("A {$req->status} request cannot move to {$status}.", 409, [], 'invalid_transition');
        }
        $req->forceFill(array_filter(['status' => $status, 'admin_note' => $note], fn ($v) => $v !== null))->save();

        return $req;
    }

    /**
     * Staff quote: creates a custom-line order (payment pending) for the shopper and texts them a pay link.
     * A guest is matched to (or signed up as) a customer by phone so they can pay after an OTP sign-in.
     */
    public function quote(ProductRequest $req, int $priceBdt, ?string $note = null, ?string $title = null, int $shippingBdt = 0): Order
    {
        if ($priceBdt < 1) {
            throw CommerceException::field('quoteBdt', 'Enter a price.');
        }
        $user = $req->user ?? $this->userFor($req);
        $product = $req->product_id ? Product::query()->find($req->product_id) : null;
        $title = $title ?: ($product?->title ?? mb_strimwidth((string) ($req->description ?: $req->url ?: 'Requested item'), 0, 120, '…'));
        $address = $user->addresses()->orderByDesc('is_default')->first()?->snapshot()
            ?? ['name' => $user->name ?: ($req->name ?: 'Customer'), 'phone' => $user->phone ?: $req->phone, 'division' => '', 'district' => '',
                'area' => '', 'line' => 'Address to be confirmed by phone'];
        $qty = max(1, (int) $req->qty);
        $orders = app(OrderService::class);
        $pricing = app(PricingService::class);

        $order = DB::transaction(function () use ($req, $user, $priceBdt, $title, $address, $qty, $orders, $pricing, $product, $shippingBdt, $note) {
            $sub = $priceBdt * $qty;
            $order = Order::query()->create([
                'id' => $orders->newOrderId(), 'user_id' => $user->id, 'status' => 'placed', 'payment_method' => 'bkash', 'payment_status' => 'pending',
                'subtotal_bdt' => $sub, 'shipping_bdt' => $shippingBdt, 'discount_bdt' => 0, 'total_bdt' => $sub + $shippingBdt, 'paid_bdt' => 0,
                'address' => $address, 'tracking_code' => $orders->newTrackingCode(), 'fx_rate' => $pricing->fx(),
                'notes' => 'Quote for product request #'.$req->id.($note ? ': '.$note : ''),
            ]);
            OrderItem::query()->create([
                'order_id' => $order->id, 'product_id' => $product?->id ?? 'REQ-'.$req->id, 'market' => $product?->market ?? ($req->market ?: 'amazon'),
                'title' => mb_substr($title, 0, 500), 'image' => $product?->image ?? $req->photo_url, 'qty' => $qty, 'unit_bdt' => $priceBdt,
                'unit_inr' => $product ? (float) $product->price_inr : 0, 'fx_rate' => $pricing->fx(), 'weight_kg' => $product ? $pricing->weightKg(null, (string) $product->category) : 0.5,
            ]);
            app(OrderWorkflow::class)->event($order, 'placed', 'admin', 'Quote for your product request');
            $req->forceFill(['status' => 'quoted', 'quote_bdt' => $priceBdt, 'quote_note' => $note, 'quote_order_id' => $order->id,
                'quoted_at' => now(), 'user_id' => $user->id])->save();

            return $order;
        });

        $link = Messenger::webUrl('/orders/'.$order->id.'?pay=1');
        $text = 'Your Skydrop Retail quote: '.mb_strimwidth($title, 0, 50, '…').' x'.$qty.' = BDT '.number_format($order->total_bdt)
            .' delivered.'.($note ? ' '.$note : '').' Pay here: '.$link;
        if (! rescue(fn () => app(Notifier::class)->custom($user, $text, 'sms'), false, true)) {
            Log::warning('[product-request] quote notification not sent', ['request' => $req->id]);
        }

        return $order;
    }

    private function userFor(ProductRequest $req): User
    {
        $phone = Phone::normalize($req->phone);
        if (! $phone) {
            throw CommerceException::field('phone', 'This request has no valid phone number.');
        }

        return User::query()->firstOrCreate(['phone' => $phone], ['name' => $req->name ?: 'Customer']);
    }

    /** Keeps "quoted" requests in sync once their quote order is paid. */
    public function sync(ProductRequest $req): ProductRequest
    {
        if ($req->status === 'quoted' && $req->quote_order_id) {
            $o = $req->quoteOrder;
            if ($o && in_array($o->payment_status, ['paid', 'partial'], true)) {
                $req->forceFill(['status' => 'ordered'])->save();
            }
        }

        return $req;
    }
}

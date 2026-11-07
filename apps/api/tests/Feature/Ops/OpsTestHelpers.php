<?php

namespace Tests\Feature\Ops;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Payment;
use App\Models\Product;
use App\Models\User;

trait OpsTestHelpers
{
    protected function staff(string $role = 'admin'): User
    {
        $u = new User;
        $u->forceFill(['name' => ucfirst($role).' Staff', 'email' => $role.'@ops.test', 'password' => 'Secret-Passw0rd!', 'is_admin' => true, 'role' => $role])->save();

        return $u;
    }

    protected function customer(string $phone = '+8801711000000', array $attrs = []): User
    {
        $u = User::query()->where('phone', $phone)->first() ?? new User;
        $u->forceFill(array_merge(['name' => 'Rahim Uddin', 'phone' => $phone, 'is_admin' => false], $attrs))->save();

        return $u;
    }

    /** A delivered-style order with one item; numbers chosen for easy arithmetic. */
    protected function makeOrder(array $o = [], array $item = [], ?User $user = null): Order
    {
        $user ??= $this->customer();
        Product::query()->firstOrCreate(['id' => $item['product_id'] ?? 'B0OPS00001'], ['market' => 'amazon', 'category' => $item['category'] ?? 'electronics',
            'title' => 'Ops product', 'price_inr' => 1000, 'mrp_inr' => 1200]);
        $order = Order::query()->create(array_merge([
            'id' => 'SR-'.strtoupper(substr(md5(uniqid('', true)), 0, 6)), 'user_id' => $user->id, 'status' => 'delivered',
            'payment_method' => 'bkash', 'payment_status' => 'paid', 'subtotal_bdt' => 2000, 'shipping_bdt' => 600, 'discount_bdt' => 0,
            'total_bdt' => 2600, 'paid_bdt' => 2600, 'fx_rate' => 1.5,
            'address' => ['name' => 'Rahim', 'phone' => $user->phone, 'division' => 'Dhaka', 'district' => 'Dhaka', 'area' => 'Mirpur', 'line' => 'House 1'],
        ], $o));
        OrderItem::query()->create(array_merge([
            'order_id' => $order->id, 'product_id' => 'B0OPS00001', 'market' => 'amazon', 'title' => 'Ops product', 'qty' => 1,
            'unit_bdt' => 2000, 'unit_inr' => 1000, 'weight_kg' => 1.0,
        ], collect($item)->except('category')->all()));

        return $order->refresh();
    }

    protected function pay(Order $order, string $gateway, float $amount, string $trx, string $status = 'paid'): Payment
    {
        return Payment::query()->create(['order_id' => $order->id, 'gateway' => $gateway, 'amount_bdt' => $amount, 'status' => $status,
            'gateway_ref' => $trx, 'paid_at' => now(), 'kind' => 'full']);
    }
}

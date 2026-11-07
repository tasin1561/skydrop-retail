<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

/** Example coupons. Everything about coupons is managed in Admin → Sales → Coupons; clients never hardcode codes. */
class CouponSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::query()->updateOrCreate(['code' => 'SKYDROP10'], [
            'type' => 'percent_items', 'value' => 10, 'max_discount_bdt' => 1000, 'min_subtotal_bdt' => 0, 'active' => true,
            'per_customer_limit' => 1, 'store' => 'all', 'featured' => true,
            'promo_text_en' => '10% off your order, up to ৳1,000', 'promo_text_bn' => 'অর্ডারে ১০% ছাড়, সর্বোচ্চ ৳১,০০০',
        ]);
        Coupon::query()->updateOrCreate(['code' => 'FREESHIP'], [
            'type' => 'free_shipping', 'value' => 0, 'max_discount_bdt' => null, 'min_subtotal_bdt' => 0, 'active' => true,
            'per_customer_limit' => 1, 'store' => 'all', 'featured' => false,
        ]);
    }
}

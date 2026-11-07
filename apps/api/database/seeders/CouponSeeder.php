<?php

namespace Database\Seeders;

use App\Models\Coupon;
use Illuminate\Database\Seeder;

class CouponSeeder extends Seeder
{
    public function run(): void
    {
        Coupon::query()->updateOrCreate(['code' => 'SKYDROP10'], [
            'type' => 'percent_items', 'value' => 10, 'max_discount_bdt' => 1000, 'min_subtotal_bdt' => 0, 'active' => true,
        ]);
        Coupon::query()->updateOrCreate(['code' => 'FREESHIP'], [
            'type' => 'free_shipping', 'value' => 0, 'max_discount_bdt' => null, 'min_subtotal_bdt' => 0, 'active' => true,
        ]);
    }
}

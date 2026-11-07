<?php

namespace Database\Seeders;

use App\Models\MembershipPlan;
use App\Services\Money\MembershipService;
use Illuminate\Database\Seeder;

/**
 * Example Skydrop Plus plans. Seeded INACTIVE: the owner reviews prices and benefits in Admin → Wallet & Plus →
 * Membership plans and switches them on. Idempotent by slug: an existing plan (possibly edited or activated by the
 * owner) is never changed.
 */
class MembershipPlanSeeder extends Seeder
{
    /** @return list<array<string, mixed>> */
    public static function plans(): array
    {
        return [
            [
                'slug' => 'plus-monthly', 'name' => 'Plus Monthly', 'price_bdt' => 299, 'period' => 'monthly', 'sort' => 1, 'highlight' => false,
                'tagline' => 'Try Plus for a month',
                'benefits' => [
                    'free_shipping_threshold_bdt' => 3000, 'shipping_discount_pct' => 10, 'points_multiplier' => 1.5,
                    'early_flash_sale_access_minutes' => 30, 'priority_support' => true,
                ],
            ],
            [
                'slug' => 'plus-quarterly', 'name' => 'Plus Quarterly', 'price_bdt' => 799, 'period' => 'quarterly', 'sort' => 2, 'highlight' => true,
                'tagline' => 'Most popular: save ৳98 vs monthly',
                'benefits' => [
                    'free_shipping_threshold_bdt' => 2000, 'shipping_discount_pct' => 15, 'points_multiplier' => 2,
                    'extra_discount_pct' => 2, 'extra_discount_cap_bdt' => 300, 'early_flash_sale_access_minutes' => 60,
                    'priority_support' => true, 'free_returns' => true,
                ],
            ],
            [
                'slug' => 'plus-yearly', 'name' => 'Plus Yearly', 'price_bdt' => 2499, 'period' => 'yearly', 'sort' => 3, 'highlight' => false,
                'tagline' => 'Best value: save ৳1,089 vs monthly',
                'benefits' => [
                    'free_shipping' => true, 'points_multiplier' => 2, 'extra_discount_pct' => 3, 'extra_discount_cap_bdt' => 500,
                    'free_unboxing' => true, 'priority_support' => true, 'early_flash_sale_access_minutes' => 120,
                    'monthly_wallet_credit_bdt' => 50, 'free_returns' => true,
                ],
            ],
        ];
    }

    public function run(): void
    {
        foreach (self::plans() as $plan) {
            MembershipPlan::query()->firstOrCreate(['slug' => $plan['slug']], [
                ...$plan, 'active' => false, 'benefits' => MembershipService::normalize($plan['benefits']),
            ]);
        }
    }
}

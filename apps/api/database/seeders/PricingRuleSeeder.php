<?php

namespace Database\Seeders;

use App\Models\PricingRule;
use Illuminate\Database\Seeder;

/** Default pricing: 10% margin everywhere, import duty and default weight per category (same as apps/web pricing.ts). */
class PricingRuleSeeder extends Seeder
{
    public function run(): void
    {
        $rules = [
            '*' => [10, 10, 0.5],
            'electronics' => [10, 15, 0.4],
            'fashion' => [10, 10, 0.6],
            'beauty' => [10, 10, 0.15],
            'grocery' => [10, 5, 1],
            'home' => [10, 10, 1.2],
        ];
        foreach ($rules as $category => [$margin, $import, $weight]) {
            PricingRule::query()->updateOrCreate(['category' => $category], [
                'margin_pct' => $margin, 'import_pct' => $import, 'default_weight_kg' => $weight,
            ]);
        }
    }
}

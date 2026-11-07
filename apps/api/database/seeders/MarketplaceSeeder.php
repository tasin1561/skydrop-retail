<?php

namespace Database\Seeders;

use App\Models\Marketplace;
use Illuminate\Database\Seeder;

/** The three connected Indian stores. live_fetch stays false until provider credentials are configured. */
class MarketplaceSeeder extends Seeder
{
    public function run(): void
    {
        foreach ([
            ['id' => 'amazon', 'name' => 'Mega Store', 'source' => 'amazon.in'],
            ['id' => 'flipkart', 'name' => 'Style Bazaar', 'source' => 'flipkart.com'],
            ['id' => 'bigbasket', 'name' => 'Daily Basket', 'source' => 'bigbasket.com'],
        ] as $m) {
            Marketplace::query()->updateOrCreate(['id' => $m['id']], $m + [
                'enabled' => true, 'live_fetch' => false, 'pincode' => config('retail.pincode', '700001'),
            ]);
        }
    }
}

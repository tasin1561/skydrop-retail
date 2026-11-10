<?php

namespace Database\Seeders;

use App\Models\Category;
use Illuminate\Database\Seeder;

/** Store categories from apps/web/src/lib/markets.ts. browse_node feeds the weekly bestseller seeding. */
class CategorySeeder extends Seeder
{
    public function run(): void
    {
        $markets = [
            'amazon' => [
                ['electronics', 'Electronics', 'electronics'],
                ['beauty', 'Beauty', 'beauty'],
                ['fashion', 'Shoes', 'shoes'],
                ['grocery', 'Drinks & Grocery', 'grocery'],
            ],
            'flipkart' => [
                ['fashion', 'Fashion', null],
                ['electronics', 'Gadgets', null],
                ['home', 'Home', null],
                ['beauty', 'Beauty', null],
            ],
            // Myntra: a listing path ("men-tshirts", most popular first) or a search query.
            'myntra' => [
                ['fashion', 'Fashion', 'men-tshirts'],
                ['beauty', 'Beauty', 'makeup'],
                ['home', 'Home', 'bedsheets'],
            ],
        ];
        foreach ($markets as $market => $cats) {
            foreach ($cats as [$slug, $name, $node]) {
                Category::query()->updateOrCreate(['market' => $market, 'slug' => $slug], [
                    'name' => $name, 'browse_node' => $node, 'active' => true, 'seed_pages' => 2,
                ]);
            }
        }
    }
}

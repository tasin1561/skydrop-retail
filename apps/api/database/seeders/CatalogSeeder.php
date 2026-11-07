<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\ProductDetail;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Imports the bundled storefront catalog (database/data/catalog.json, copied from apps/web/src/data/catalog.json:
 * real amazon.in data fetched via Oxylabs with pincode 700001, plus demo Flipkart / BigBasket items).
 */
class CatalogSeeder extends Seeder
{
    public function run(): void
    {
        $data = json_decode((string) file_get_contents(database_path('data/catalog.json')), true);
        $now = now();
        $products = $data['products'] ?? [];
        DB::transaction(function () use ($products, $now) {
            foreach ($products as $i => $p) {
                $demo = (bool) ($p['demo'] ?? false);
                $stock = (string) ($p['stock'] ?? '');
                $inStock = ! preg_match('/out of stock|unavailable/i', $stock);
                Product::query()->updateOrCreate(['id' => $p['id']], [
                    'market' => $p['market'], 'category' => $p['category'], 'title' => $p['title'], 'brand' => $p['brand'] ?? null,
                    'price_inr' => $p['priceInr'], 'mrp_inr' => max($p['mrpInr'] ?? 0, $p['priceInr']),
                    'rating' => $p['rating'] ?? 0, 'reviews' => $p['reviews'] ?? 0, 'image' => $p['image'] ?? null,
                    'prime' => $p['prime'] ?? false, 'best_seller' => $p['bestSeller'] ?? false, 'amazons_choice' => $p['amazonsChoice'] ?? false,
                    'sales_volume' => $p['salesVolume'] ?? '', 'in_stock' => $inStock, 'hidden' => false,
                    'detailed' => $p['detailed'] ?? false, 'demo' => $demo, 'placeholder' => $p['placeholder'] ?? null,
                    'pincode_verified' => $p['market'] === 'amazon' && ! $demo, 'source' => $demo ? 'demo' : 'seed',
                    'saving_inr' => max(0, ($p['mrpInr'] ?? 0) - $p['priceInr']),
                    'price_checked_at' => $now, 'details_fetched_at' => ($p['detailed'] ?? false) ? $now : null,
                    // keeps catalog.json order for equal sort keys (catalog.ts sorts are stable)
                    'created_at' => $now->copy()->subSeconds(count($products) - $i), 'updated_at' => $now,
                ]);
                ProductDetail::query()->updateOrCreate(['product_id' => $p['id']], [
                    'images' => $p['images'] ?? [], 'videos' => $p['videos'] ?? [], 'bullets' => $p['bullets'] ?? [],
                    'specs' => (object) ($p['specs'] ?? []), 'variants' => $p['variants'] ?? [], 'aplus' => $p['aplus'] ?? [],
                    'reviews' => $p['reviewList'] ?? [], 'star_breakdown' => $p['starBreakdown'] ?? [],
                    'category_path' => $p['categoryPath'] ?? [], 'offers' => [], 'weight' => $p['weight'] ?? null,
                    'stock_text' => $stock !== '' ? $stock : null, 'delivery_text' => $p['deliveryKolkata'] ?? null,
                    'has_videos' => (bool) ($p['hasVideos'] ?? false),
                ]);
            }
        });
    }
}

<?php

namespace Tests\Feature\Categories;

use App\Models\Category;
use App\Models\Product;
use App\Services\Categories\CategoryTree;
use Database\Seeders\CategoryTreeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Feature\Catalog\CatalogTestHelpers;
use Tests\TestCase;

class CategoryCoversTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->seed(CategoryTreeSeeder::class);
        CategoryTree::flush();
    }

    private function product(string $id, string $market, string $path, string $brand, float $rating, int $reviews, array $extra = []): void
    {
        Product::query()->create($extra + [
            'id' => $id, 'market' => $market, 'category' => 'fashion', 'title' => "$brand item", 'brand' => $brand,
            'price_inr' => 500, 'mrp_inr' => 900, 'rating' => $rating, 'reviews' => $reviews, 'image' => "https://img.test/$id.jpg",
            'category_id' => Category::query()->where('path', $path)->value('id'), 'category_source' => 'auto', 'hidden' => false, 'in_stock' => true,
        ]);
    }

    public function test_every_category_gets_photos_from_its_best_products_with_varied_brands(): void
    {
        $kurtas = 'fashion/women/clothing/kurtas-kurtis';
        $this->product('my1', 'myntra', $kurtas, 'Libas', 4.4, 2000);
        $this->product('my2', 'myntra', $kurtas, 'Libas', 4.5, 3000);      // same brand: comes after other brands
        $this->product('fk1', 'flipkart', $kurtas, 'Anouk', 4.2, 900);
        $this->product('B0AMZ00001', 'amazon', $kurtas, 'Generic', 4.3, 5000);
        $this->product('my3', 'myntra', $kurtas, 'Biba', 2.1, 9000);      // badly rated: last
        $this->product('my4', 'myntra', $kurtas, 'Hidden', 5.0, 9999, ['hidden' => true]);
        $this->product('my5', 'myntra', $kurtas, 'Sold out', 5.0, 9999, ['in_stock' => false]);
        $this->product('my6', 'myntra', 'fashion/women/clothing/sarees', 'Saree Co', 4.6, 100);

        $this->artisan('retail:category-covers')->assertSuccessful();

        $node = collect($this->getJson('/api/v1/categories?store=all')->assertOk()->json('items'))->firstWhere('path', 'fashion');
        $this->assertNotNull($node['image'], 'a department shows the photos of its whole subtree');
        $this->assertCount(4, $node['images']);

        $kurtaNode = app(CategoryTree::class)->present(app(CategoryTree::class)->byPath($kurtas));
        $this->assertSame('https://img.test/my2.jpg', $kurtaNode['image'], 'best rated and reviewed model photo first');
        $this->assertSame(['my2', 'fk1', 'B0AMZ00001'], array_map(fn ($u) => basename($u, '.jpg'), array_slice($kurtaNode['images'], 0, 3)), 'one photo per brand first');
        $this->assertNotContains('https://img.test/my4.jpg', $kurtaNode['images']);
        $this->assertNotContains('https://img.test/my5.jpg', $kurtaNode['images']);

        $empty = app(CategoryTree::class)->present(app(CategoryTree::class)->byPath('automotive'));
        $this->assertNull($empty['image'], 'no products: the apps show the icon');
        $this->assertSame([], $empty['images']);
    }

    public function test_admin_photo_stays_in_front(): void
    {
        $this->product('my1', 'myntra', 'fashion/women/clothing/sarees', 'Saree Co', 4.6, 100);
        Category::query()->where('path', 'fashion/women/clothing/sarees')->firstOrFail()->update(['image' => 'https://cdn.test/sarees.jpg']);
        $this->artisan('retail:category-covers')->assertSuccessful();

        $n = app(CategoryTree::class)->present(app(CategoryTree::class)->byPath('fashion/women/clothing/sarees'));
        $this->assertSame('https://cdn.test/sarees.jpg', $n['image']);
        $this->assertSame(['https://cdn.test/sarees.jpg', 'https://img.test/my1.jpg'], $n['images']);
    }
}

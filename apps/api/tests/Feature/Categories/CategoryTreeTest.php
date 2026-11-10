<?php

namespace Tests\Feature\Categories;

use App\Jobs\Catalog\FetchSearchJob;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductDetail;
use App\Services\Categories\CategoryMatcher;
use App\Services\Categories\CategoryTree;
use App\Services\Categories\CategoryWriter;
use App\Sources\Parsers\AmazonHtmlParser;
use Database\Seeders\CategoryTreeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Feature\Catalog\CatalogTestHelpers;
use Tests\TestCase;

class CategoryTreeTest extends TestCase
{
    use CatalogTestHelpers, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedCatalog();
        $this->seed(CategoryTreeSeeder::class);
        Http::preventStrayRequests();
    }

    private function matcher(): CategoryMatcher
    {
        return app(CategoryMatcher::class);
    }

    public function test_tree_seeder_is_idempotent_and_shaped_like_the_spec(): void
    {
        $count = Category::query()->tree()->count();
        $ids = Category::query()->tree()->orderBy('path')->pluck('id', 'path')->all();
        $this->seed(CategoryTreeSeeder::class);

        $this->assertSame($count, Category::query()->tree()->count());
        $this->assertSame($ids, Category::query()->tree()->orderBy('path')->pluck('id', 'path')->all());
        $levels = Category::query()->tree()->selectRaw('depth, count(*) n')->groupBy('depth')->pluck('n', 'depth')->all();
        $this->assertGreaterThanOrEqual(10, $levels[1]);
        $this->assertGreaterThanOrEqual(80, $levels[2]);
        $leaves = Category::query()->tree()->whereNotIn('id', Category::query()->tree()->whereNotNull('parent_id')->select('parent_id'))->count();
        $this->assertGreaterThanOrEqual(300, $leaves);
        $this->assertLessThanOrEqual(500, $leaves);

        $tee = Category::query()->where('path', 'fashion/men/clothing/t-shirts-polos')->firstOrFail();
        $this->assertSame(4, $tee->depth);
        $this->assertSame('fashion', $tee->effectivePricingGroup());
        $this->assertNotEmpty($tee->name_bn);
        $this->assertNull($tee->sources['amazon']['node']); // never invented
        $this->assertSame('fashion', $tee->sources['amazon']['search_alias']);
        $this->assertArrayHasKey('myntra', $tee->sources);
        $this->assertArrayNotHasKey('bigbasket', $tee->sources);
        // legacy per-store rows still used by retail:seed-bestsellers
        $this->assertTrue(Category::query()->where('market', 'amazon')->where('slug', 'electronics')->exists());
    }

    public function test_matcher_maps_amazon_breadcrumbs_from_the_parser_fixture(): void
    {
        $p = (new AmazonHtmlParser)->parse($this->amazonHtml(), 'B0FMDLD86P');
        $m = $this->matcher()->match('amazon', $p['title'], $p['category_path'], 'electronics');

        $this->assertSame('mobiles-electronics/headphones-audio/true-wireless-earbuds', $m['path']);
        $this->assertSame('electronics', $m['pricing_group']);
    }

    public function test_matcher_on_sample_breadcrumbs_and_titles(): void
    {
        $cases = [
            ['amazon', 'Campus Men Wells Running Shoes', ['Shoes & Handbags', 'Shoes', "Men's Shoes", 'Sports & Outdoor Shoes', 'Running Shoes'], 'fashion/men/footwear/sports-shoes'],
            ['amazon', 'Real Fruit Power Mixed Fruit 1L', ['Grocery & Gourmet Foods', 'Coffee, Tea & Beverages', 'Juices', 'Fruit Juice'], 'grocery-gourmet/beverages/juices'],
            ['flipkart', 'Roadster Men Solid Round Neck Cotton T-Shirt', ['Home', 'Clothing and Accessories', 'Topwear', 'T-Shirts', "Men's T-Shirts"], 'fashion/men/clothing/t-shirts-polos'],
            ['amazon', 'Surf Excel Easy Wash Detergent Powder 1 kg', ['Cleaning & Household', 'Detergents & Fabric Care', 'Detergent Powder, Bars'], 'home-kitchen/household/detergents-laundry'],
            ['amazon', 'iQOO Z11 Lite 44W 5G (Midnight Blue, 4GB RAM, 64GB Storage)', [], 'mobiles-electronics/mobiles/smartphones'],
            ['amazon', 'MARS Creamy Matte Long Lasting Lipstick for Women | Creamy Lipstick', [], 'beauty-personal-care/makeup/lip-makeup'],
            ['amazon', 'Zapcase Back Case Cover for OnePlus Nord CE 3 5G', [], 'mobiles-electronics/mobile-accessories/cases-covers'],
            ['amazon', 'Dishwash Liquid 750 ml', [], 'home-kitchen/household/dishwash'],
            ['amazon', 'Basmati Rice, Extra Long Grain 5 kg', [], 'grocery-gourmet/staples/rice-rice-products'],
        ];
        foreach ($cases as [$market, $title, $crumbs, $path]) {
            $this->assertSame($path, $this->matcher()->match($market, $title, $crumbs)['path'] ?? null, $title);
        }
        $this->assertNull($this->matcher()->match('amazon', 'Zzqx 1 L'));
    }

    public function test_observer_assigns_on_upsert_and_breadcrumbs_refine_it(): void
    {
        $p = Product::query()->create(['id' => 'B0TEST0001', 'market' => 'amazon', 'category' => 'electronics', 'title' => 'boAt Rockerz 255 Pro+ Wireless Neckband', 'price_inr' => 999, 'mrp_inr' => 1999]);
        $this->assertSame('mobiles-electronics/headphones-audio/neckband-earphones', Category::query()->find($p->category_id)->path);
        $this->assertSame('auto', $p->category_source);

        // A detail fetch with breadcrumbs re-matches the product.
        ProductDetail::query()->create(['product_id' => $p->id, 'category_path' => ['Electronics', 'Headphones, Earbuds & Accessories', 'Earbuds', 'True Wireless']]);
        $this->assertSame('mobiles-electronics/headphones-audio/true-wireless-earbuds', Category::query()->find($p->fresh()->category_id)->path);

        // Manual assignments stick.
        $node = Category::query()->where('path', 'mobiles-electronics/headphones-audio/wired-earphones')->firstOrFail();
        app(CategoryWriter::class)->assign([$p->id], $node);
        $p->refresh()->update(['title' => 'boAt Airdopes 141 TWS Earbuds']);
        $this->assertSame($node->id, $p->fresh()->category_id);
        $this->assertSame('manual', $p->fresh()->category_source);
    }

    public function test_seed_hint_files_products_under_the_node_and_keeps_the_pricing_group(): void
    {
        $node = Category::query()->where('path', 'grocery-gourmet/beverages/tea')->firstOrFail();
        $p = Product::query()->create(['id' => 'B0TEST0002', 'market' => 'amazon', 'category' => 'cat:'.$node->id, 'title' => 'Some Brand Gold 500 g', 'price_inr' => 300, 'mrp_inr' => 300]);
        $this->assertSame($node->id, $p->category_id);
        $this->assertSame('hint', $p->category_source);
        $this->assertSame('grocery', $p->category);
    }

    public function test_categories_endpoint_filters_by_store_and_depth(): void
    {
        app(CategoryWriter::class)->categorize();

        $all = $this->getJson('/api/v1/categories?depth=1')->assertOk()->json('items');
        $this->assertCount(13, $all);
        $this->assertSame([], $all[0]['children']);

        $myntra = $this->getJson('/api/v1/categories?store=myntra')->assertOk()->json('items');
        $paths = array_column($myntra, 'path');
        $this->assertContains('fashion', $paths);
        $this->assertContains('home-kitchen', $paths);
        $this->assertNotContains('mobiles-electronics', $paths);
        $this->assertNotContains('grocery-gourmet', $paths);
        $home = collect($myntra)->firstWhere('path', 'home-kitchen');
        $this->assertEqualsCanonicalizing(['home-kitchen/home-decor', 'home-kitchen/furnishing'], array_column($home['children'], 'path'));
        foreach ($myntra as $n) {
            $this->assertContains('myntra', $n['stores']);
        }
        $this->getJson('/api/v1/categories?store=bigbasket')->assertStatus(422);

        $grocery = collect($this->getJson('/api/v1/categories?store=amazon')->json('items'))->firstWhere('path', 'grocery-gourmet');
        $this->assertGreaterThan(50, $grocery['count']);
        $this->assertSame('মুদি ও গুরমেট খাবার', $grocery['nameBn']);
    }

    public function test_category_detail_has_breadcrumbs_children_siblings_brands_and_price_range(): void
    {
        app(CategoryWriter::class)->categorize();
        $r = $this->getJson('/api/v1/categories/grocery-gourmet/beverages')->assertOk();
        $this->assertSame(['grocery-gourmet', 'grocery-gourmet/beverages'], array_column($r->json('breadcrumbs'), 'path'));
        $this->assertContains('grocery-gourmet/beverages/juices', array_column($r->json('children'), 'path'));
        $this->assertContains('grocery-gourmet/staples', array_column($r->json('siblings'), 'path'));
        $this->assertNotEmpty($r->json('brands'));
        $this->assertGreaterThan(0, $r->json('priceRange.minBdt'));
        $this->assertGreaterThanOrEqual($r->json('priceRange.minBdt'), $r->json('priceRange.maxBdt'));

        $this->getJson('/api/v1/categories/nope/nothing')->assertNotFound();
    }

    public function test_search_with_a_category_path_includes_descendants_and_returns_facets(): void
    {
        app(CategoryWriter::class)->categorize();
        $juices = Category::query()->where('path', 'grocery-gourmet/beverages/juices')->firstOrFail();
        $inJuices = Product::query()->where('hidden', false)->where('category_id', $juices->id)->count();
        $this->assertGreaterThan(0, $inJuices);

        $leaf = $this->getJson('/api/v1/search?q=&category=grocery-gourmet/beverages/juices')->assertOk();
        $this->assertSame($inJuices, $leaf->json('total'));

        $parent = $this->getJson('/api/v1/search?q=&category=grocery-gourmet/beverages')->assertOk();
        $this->assertGreaterThan($inJuices, $parent->json('total')); // tea, soft drinks, coconut water… included
        $facets = collect($parent->json('facets.categories'));
        $this->assertSame($inJuices, $facets->firstWhere('path', 'grocery-gourmet/beverages/juices')['count']);
        $this->assertSame($parent->json('total'), $facets->sum('count'));

        // the five pricing groups keep the legacy filter on products.category
        $legacy = $this->getJson('/api/v1/search?q=&category=grocery')->assertOk();
        $this->assertSame(Product::query()->where('hidden', false)->where('category', 'grocery')->count(), $legacy->json('total'));
    }

    public function test_moving_a_node_recomputes_paths_of_the_subtree(): void
    {
        $node = Category::query()->where('path', 'health-wellness/sports-nutrition')->firstOrFail();
        $sports = Category::query()->where('path', 'sports-fitness')->firstOrFail();

        app(CategoryWriter::class)->move($node, $sports->id);

        $this->assertSame('sports-fitness/sports-nutrition', $node->fresh()->path);
        $this->assertSame(2, $node->fresh()->depth);
        $leaf = Category::query()->where('path', 'sports-fitness/sports-nutrition/whey-protein')->firstOrFail();
        $this->assertSame(3, $leaf->depth);
        $this->assertStringStartsWith($sports->sort_key.'.', $leaf->sort_key);
        $this->assertFalse(Category::query()->where('path', 'like', 'health-wellness/sports-nutrition%')->exists());
        $this->assertNotNull(app(CategoryTree::class)->byPath('sports-fitness/sports-nutrition/whey-protein')); // cache busted

        $this->expectException(\InvalidArgumentException::class);
        app(CategoryWriter::class)->move($sports, $leaf->id); // not inside itself
    }

    public function test_seed_category_dispatches_the_catalog_search_job_per_leaf(): void
    {
        Queue::fake();
        $this->goLive('amazon');
        $this->artisan('retail:seed-category', ['path' => 'mobiles-electronics/wearables', '--store' => 'amazon', '--pages' => 1])->assertSuccessful();

        $node = Category::query()->where('path', 'mobiles-electronics/wearables/smartwatches')->firstOrFail();
        Queue::assertPushed(FetchSearchJob::class, 4); // four leaves, one page each
        Queue::assertPushed(FetchSearchJob::class, fn (FetchSearchJob $j) => $j->market === 'amazon' && $j->query === 'smartwatches' && $j->category === 'cat:'.$node->id);

        Queue::fake();
        $this->artisan('retail:seed-category', ['path' => 'mobiles-electronics/wearables', '--store' => 'flipkart'])->assertSuccessful();
        Queue::assertNothingPushed(); // flipkart is not live
    }

    public function test_categorize_command_backfills_and_recounts(): void
    {
        $this->artisan('retail:categorize', ['--all' => true])->assertSuccessful();
        $this->assertLessThan(10, Product::query()->whereNull('category_id')->count());
        $this->assertGreaterThan(0, Category::query()->where('path', 'grocery-gourmet')->value('product_count'));
    }
}

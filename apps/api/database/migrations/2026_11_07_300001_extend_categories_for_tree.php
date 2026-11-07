<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Unified Skydrop Retail category tree (database/data/category_tree.json).
 *
 * The old per-store rows (market = amazon|flipkart|bigbasket, used by retail:seed-bestsellers through browse_node)
 * keep working unchanged. Unified tree nodes use market = 'all' and are keyed by their materialised `path`
 * (e.g. fashion/men/clothing/t-shirts-polos); `slug` is the last path segment and is no longer unique per market,
 * so the (market, slug) unique index is replaced by a unique index on `path`.
 *
 * Products get a nullable `category_id` (deepest matching node, normally a leaf) plus `category_source`
 * (auto | hint | manual) so admin assignments are never overwritten by the matcher.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropUnique(['market', 'slug']);
        });

        Schema::table('categories', function (Blueprint $table) {
            $table->string('path')->nullable()->unique();      // fashion/men/clothing/t-shirts-polos (null on legacy rows)
            $table->string('sort_key')->nullable()->index();   // 0003.0001.0001: tree order for listings
            $table->unsignedTinyInteger('depth')->default(1);  // 1 = top level
            $table->string('name_bn')->nullable();
            $table->string('icon', 40)->nullable();            // web Icon key
            $table->unsignedSmallInteger('sort')->default(0);
            $table->string('pricing_group', 20)->nullable();   // electronics|fashion|beauty|grocery|home; null = inherit
            $table->json('sources')->nullable();               // {amazon:{search_alias,query,node}, flipkart:{query,path?}, bigbasket:{query,slug?}}
            $table->json('keywords')->nullable();
            $table->string('image', 1000)->nullable();
            $table->boolean('featured')->default(false);
            $table->unsignedInteger('product_count')->default(0); // cached, subtree incl. descendants (retail:categorize --recount)
            $table->index(['market', 'parent_id']);
        });

        Schema::table('products', function (Blueprint $table) {
            $table->unsignedBigInteger('category_id')->nullable()->index();
            $table->string('category_source', 10)->nullable(); // auto | hint | manual
        });
    }

    public function down(): void
    {
        Schema::table('products', function (Blueprint $table) {
            $table->dropIndex(['category_id']);
            $table->dropColumn(['category_id', 'category_source']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->dropIndex(['market', 'parent_id']);
            $table->dropUnique(['path']);
            $table->dropIndex(['sort_key']);
            $table->dropColumn(['path', 'sort_key', 'depth', 'name_bn', 'icon', 'sort', 'pricing_group', 'sources', 'keywords',
                'image', 'featured', 'product_count']);
        });
        Schema::table('categories', function (Blueprint $table) {
            $table->unique(['market', 'slug']);
        });
    }
};

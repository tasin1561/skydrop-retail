<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marketplaces', function (Blueprint $table) {
            $table->string('id', 20)->primary(); // amazon | flipkart | bigbasket
            $table->string('name');              // storefront name, e.g. "Mega Store"
            $table->string('source');            // shopper-facing source domain, e.g. "amazon.in"
            $table->boolean('enabled')->default(true);
            $table->boolean('live_fetch')->default(false); // false = catalog only (demo), true = call providers
            $table->string('pincode', 10)->default('700001');
            $table->unsignedTinyInteger('budget_share')->default(100); // % of the monthly provider budget
            $table->json('settings')->nullable();
            $table->timestamps();
        });

        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->string('market', 20)->index();
            $table->string('slug');
            $table->string('name');
            $table->string('browse_node')->nullable(); // marketplace category id
            $table->foreignId('parent_id')->nullable();
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('seed_pages')->default(2);
            $table->timestamps();
            $table->unique(['market', 'slug']);
        });

        Schema::create('products', function (Blueprint $table) {
            $table->string('id', 40)->primary(); // ASIN or marketplace id
            $table->string('market', 20)->index();
            $table->string('parent_id', 40)->nullable()->index(); // variant family
            $table->string('category', 40)->index();
            $table->string('title', 500);
            $table->string('brand')->nullable();
            $table->decimal('price_inr', 12, 2)->default(0);
            $table->decimal('mrp_inr', 12, 2)->default(0);
            $table->decimal('rating', 3, 2)->default(0);
            $table->unsignedInteger('reviews')->default(0);
            $table->string('image', 1000)->nullable();
            $table->boolean('prime')->default(false);
            $table->boolean('best_seller')->default(false);
            $table->boolean('amazons_choice')->default(false);
            $table->string('sales_volume')->nullable();
            $table->boolean('in_stock')->default(true);
            $table->boolean('hidden')->default(false); // unavailable or undeliverable: off listings, page kept for SEO
            $table->boolean('detailed')->default(false);
            $table->boolean('demo')->default(false);
            $table->boolean('pincode_verified')->default(false);
            $table->string('source', 20)->default('seed'); // provider that last wrote it
            $table->json('placeholder')->nullable();
            $table->timestamp('price_checked_at')->nullable()->index();
            $table->timestamp('details_fetched_at')->nullable();
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
            $table->index(['market', 'category', 'price_inr']);
        });

        Schema::create('product_details', function (Blueprint $table) {
            $table->string('product_id', 40)->primary();
            $table->json('images')->nullable();
            $table->json('videos')->nullable();
            $table->json('bullets')->nullable();
            $table->json('specs')->nullable();
            $table->json('variants')->nullable();
            $table->json('aplus')->nullable();
            $table->json('reviews')->nullable();
            $table->json('star_breakdown')->nullable();
            $table->json('category_path')->nullable();
            $table->string('weight')->nullable();
            $table->string('stock_text')->nullable();
            $table->string('delivery_text')->nullable();
            $table->boolean('has_videos')->default(false);
            $table->timestamps();
        });

        Schema::create('search_cache', function (Blueprint $table) {
            $table->id();
            $table->string('cache_key')->unique();
            $table->string('market', 20);
            $table->string('query');
            $table->unsignedSmallInteger('page')->default(1);
            $table->string('sort', 20)->default('relevance');
            $table->json('product_ids');
            $table->unsignedInteger('total')->default(0);
            $table->string('source', 20);
            $table->timestamp('fetched_at')->index();
            $table->timestamps();
        });

        Schema::create('price_history', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 40)->index();
            $table->decimal('price_inr', 12, 2);
            $table->boolean('in_stock')->default(true);
            $table->timestamp('recorded_at')->index();
        });

        Schema::create('raw_pages', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 40)->index();
            $table->binary('html_gz');
            $table->timestamp('fetched_at')->index();
        });

        Schema::create('fetch_jobs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('type', 20); // search | product | variants | verify | seed
            $table->string('key')->index();
            $table->string('status', 12)->default('queued'); // queued | running | done | failed
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->string('provider_used', 30)->nullable();
            $table->json('result')->nullable();
            $table->text('error')->nullable();
            $table->timestamps();
        });

        Schema::create('provider_health', function (Blueprint $table) {
            $table->string('provider', 30)->primary();
            $table->string('state', 10)->default('closed'); // closed | open | half_open
            $table->unsignedInteger('fail_count')->default(0);
            $table->timestamp('opened_at')->nullable();
            $table->timestamp('last_success_at')->nullable();
            $table->timestamp('last_failure_at')->nullable();
            $table->timestamps();
        });

        Schema::create('api_logs', function (Blueprint $table) {
            $table->id();
            $table->string('provider', 30)->index();
            $table->string('type', 20);
            $table->string('market', 20)->nullable();
            $table->string('key')->nullable();
            $table->unsignedSmallInteger('status');
            $table->unsignedInteger('ms');
            $table->boolean('rendered')->default(false);
            $table->decimal('cost_usd', 10, 6)->default(0);
            $table->string('error', 500)->nullable();
            $table->timestamp('created_at')->index();
        });

        Schema::create('fx_rates', function (Blueprint $table) {
            $table->id();
            $table->string('pair', 10)->default('INRBDT');
            $table->decimal('rate', 10, 5);
            $table->string('source', 30)->default('manual');
            $table->timestamp('fetched_at');
        });

        Schema::create('pricing_rules', function (Blueprint $table) {
            $table->id();
            $table->string('category', 40)->unique(); // '*' = default
            $table->decimal('margin_pct', 5, 2)->default(10);
            $table->decimal('import_pct', 5, 2)->default(10);
            $table->decimal('default_weight_kg', 6, 3)->default(0.5);
            $table->timestamps();
        });

        Schema::create('settings', function (Blueprint $table) {
            $table->string('key')->primary();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['settings', 'pricing_rules', 'fx_rates', 'api_logs', 'provider_health', 'fetch_jobs', 'raw_pages',
            'price_history', 'search_cache', 'product_details', 'products', 'categories', 'marketplaces'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Shopping module: compare stores (product_matches), bundles, group buy, pre-orders and product requests.
return new class extends Migration
{
    public function up(): void
    {
        // Same product on several listings/stores. One row per unordered pair (product_id < match_id).
        Schema::create('product_matches', function (Blueprint $table) {
            $table->id();
            $table->string('product_id', 40)->index();
            $table->string('match_id', 40)->index();
            $table->string('method', 10); // gtin | model | title
            $table->decimal('score', 4, 3)->default(0);
            $table->string('status', 12)->default('pending'); // pending | confirmed | rejected
            $table->foreignId('reviewed_by')->nullable();
            $table->timestamps();
            $table->unique(['product_id', 'match_id']);
        });

        Schema::create('bundles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 80)->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->string('image', 1000)->nullable();
            $table->decimal('discount_pct', 5, 2)->default(5); // extra discount, capped at the items' margin
            $table->boolean('active')->default(true);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->unsignedSmallInteger('sort')->default(0);
            $table->timestamps();
        });

        Schema::create('bundle_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bundle_id')->constrained()->cascadeOnDelete();
            $table->string('product_id', 40)->index();
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->unique(['bundle_id', 'product_id']);
        });

        Schema::create('group_buys', function (Blueprint $table) {
            $table->id();
            $table->string('code', 12)->unique();
            $table->string('market', 20);
            $table->foreignId('owner_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 12)->default('open')->index(); // open | closed | cancelled
            $table->unsignedTinyInteger('min_members')->default(2);
            $table->timestamp('closes_at')->index();
            $table->timestamp('closed_at')->nullable();
            $table->string('close_note')->nullable();
            $table->decimal('shipping_bdt', 12, 2)->nullable(); // consolidated shipping at close
            $table->timestamps();
        });

        Schema::create('group_buy_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('group_buy_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->json('items'); // [{productId, variant, qty}]
            $table->unsignedBigInteger('address_id')->nullable();
            $table->json('address')->nullable(); // inline address when no address_id
            $table->string('payment', 20)->default('bkash');
            $table->string('status', 12)->default('joined'); // joined | left | ordered | failed
            $table->string('order_id', 12)->nullable();
            $table->decimal('share_bdt', 12, 2)->nullable();
            $table->string('error')->nullable();
            $table->timestamps();
            $table->unique(['group_buy_id', 'user_id']);
        });

        // Pre-orders: products or categories flagged by the admin, with a deposit % and an ETA.
        Schema::create('preorder_rules', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 10); // product | category
            $table->string('target', 40); // product id or category slug
            $table->string('market', 20)->nullable(); // category rules: limit to one store (null = all)
            $table->decimal('deposit_pct', 5, 2)->default(30);
            $table->unsignedSmallInteger('eta_days')->default(21);
            $table->string('note')->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->index(['scope', 'target']);
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->unsignedBigInteger('group_id')->nullable()->index();
            $table->decimal('group_discount_bdt', 12, 2)->default(0);
            $table->decimal('bundle_discount_bdt', 12, 2)->default(0);
            $table->boolean('is_preorder')->default(false)->index();
            $table->string('preorder_state', 16)->nullable(); // awaiting_stock | balance_due | ready
            $table->decimal('deposit_pct', 5, 2)->nullable();
            $table->decimal('deposit_bdt', 12, 2)->nullable();
            $table->date('preorder_eta')->nullable();
            $table->timestamp('balance_reminded_at')->nullable();
        });

        Schema::create('product_requests', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name')->nullable();
            $table->string('phone', 20)->nullable()->index();
            $table->string('url', 2000)->nullable();
            $table->text('description')->nullable();
            $table->string('photo_url', 1000)->nullable();
            $table->unsignedSmallInteger('qty')->default(1);
            $table->unsignedInteger('budget_bdt')->nullable();
            $table->string('search_query')->nullable();
            $table->string('source', 12)->default('web'); // web | search | header | app
            $table->string('status', 12)->default('new')->index(); // new | sourcing | quoted | ordered | declined
            $table->string('market', 20)->nullable();
            $table->string('product_id', 40)->nullable(); // resolved catalog product
            $table->decimal('quote_bdt', 12, 2)->nullable();
            $table->text('quote_note')->nullable();
            $table->string('quote_order_id', 12)->nullable();
            $table->timestamp('quoted_at')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropIndex(['group_id']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropIndex(['is_preorder']));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['group_id', 'group_discount_bdt', 'bundle_discount_bdt', 'is_preorder',
            'preorder_state', 'deposit_pct', 'deposit_bdt', 'preorder_eta', 'balance_reminded_at']));
        foreach (['product_requests', 'preorder_rules', 'group_buy_members', 'group_buys', 'bundle_items', 'bundles', 'product_matches'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Commerce module additions: loyalty points, watches, reviews, support requests and extra order/payment columns.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->integer('points_balance')->default(0); // cached sum of points_ledger
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->string('payment_via', 20)->nullable();       // wallet used for advance50 (bkash|nagad|card)
            $table->decimal('coupon_discount_bdt', 12, 2)->default(0);
            $table->unsignedInteger('points_redeemed')->default(0);
            $table->decimal('points_discount_bdt', 12, 2)->default(0);
            $table->unsignedInteger('points_to_earn')->default(0);
            $table->unsignedInteger('points_earned')->default(0);
            $table->string('return_url', 500)->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
        });

        Schema::table('order_items', function (Blueprint $table) {
            $table->decimal('fx_rate', 10, 5)->nullable(); // INR->BDT rate used for unit_bdt at order time
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('kind', 20)->default('full'); // full | advance | balance (COD) | free
            $table->decimal('refunded_bdt', 12, 2)->default(0);
            $table->string('refund_ref')->nullable();
            $table->timestamp('paid_at')->nullable();
        });

        Schema::create('points_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('order_id', 12)->nullable()->index();
            $table->string('type', 10); // earn | redeem | expire | adjust | refund | signup
            $table->integer('points'); // signed
            $table->string('note')->nullable();
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('settled_at')->nullable(); // positive lot fully used or expired
            $table->timestamps();
            $table->index(['user_id', 'created_at']);
        });

        Schema::create('product_watches', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_id', 40)->index();
            $table->string('type', 20); // price_drop | back_in_stock
            $table->decimal('target_bdt', 12, 2)->nullable();
            $table->decimal('baseline_bdt', 12, 2)->nullable(); // price when the watch was created
            $table->boolean('active')->default(true);
            $table->timestamp('notified_at')->nullable();
            $table->timestamps();
            $table->unique(['user_id', 'product_id', 'type']);
        });

        Schema::create('product_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_id', 40)->index();
            $table->string('order_id', 12)->nullable();
            $table->unsignedTinyInteger('rating');
            $table->string('title')->nullable();
            $table->text('body');
            $table->boolean('verified_purchase')->default(false);
            $table->string('status', 20)->default('pending'); // pending | approved | rejected
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('support_requests', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 12)->index();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 20); // return | damaged | wrong_item | other
            $table->text('message');
            $table->string('status', 20)->default('open'); // open | in_review | resolved | rejected
            $table->json('photos')->nullable();
            $table->text('admin_note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['support_requests', 'product_reviews', 'product_watches', 'points_ledger'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('payments', fn (Blueprint $t) => $t->dropColumn(['kind', 'refunded_bdt', 'refund_ref', 'paid_at']));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('fx_rate'));
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['payment_via', 'coupon_discount_bdt', 'points_redeemed',
            'points_discount_bdt', 'points_to_earn', 'points_earned', 'return_url', 'delivered_at', 'cancelled_at']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('points_balance'));
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Coupons fully controlled from the admin: schedule, per-customer limit, first order only, store, allowed phones, storefront promo.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coupons', function (Blueprint $table) {
            if (! Schema::hasColumn('coupons', 'starts_at')) {
                $table->timestamp('starts_at')->nullable(); // empty = live now; expires_at is the end
            }
            if (! Schema::hasColumn('coupons', 'per_customer_limit')) {
                $table->unsignedInteger('per_customer_limit')->nullable(); // null = unlimited
            }
            if (! Schema::hasColumn('coupons', 'first_order_only')) {
                $table->boolean('first_order_only')->default(false);
            }
            if (! Schema::hasColumn('coupons', 'store')) {
                $table->string('store', 20)->default('all'); // all | amazon | flipkart | bigbasket
            }
            if (! Schema::hasColumn('coupons', 'allowed_phones')) {
                $table->json('allowed_phones')->nullable(); // ["+8801…"]; null/empty = everyone
            }
            if (! Schema::hasColumn('coupons', 'featured')) {
                $table->boolean('featured')->default(false)->index(); // shown on the storefront promo strip
            }
            if (! Schema::hasColumn('coupons', 'promo_text_en')) {
                $table->string('promo_text_en', 120)->nullable();
                $table->string('promo_text_bn', 120)->nullable();
            }
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'coupon_id')) {
                $table->unsignedBigInteger('coupon_id')->nullable()->index(); // per-customer limit + coupon stats
            }
        });

        // Older orders only kept the code.
        foreach (DB::table('coupons')->get(['id', 'code']) as $c) {
            DB::table('orders')->whereNull('coupon_id')->where('coupon_code', $c->code)->update(['coupon_id' => $c->id]);
        }
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('coupon_id'));
        Schema::table('coupons', fn (Blueprint $t) => $t->dropColumn(['starts_at', 'per_customer_limit', 'first_order_only', 'store',
            'allowed_phones', 'featured', 'promo_text_en', 'promo_text_bn']));
    }
};

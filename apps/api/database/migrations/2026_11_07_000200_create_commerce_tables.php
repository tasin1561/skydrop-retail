<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('otp_codes', function (Blueprint $table) {
            $table->id();
            $table->string('phone', 20)->index();
            $table->string('code_hash');
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('expires_at');
            $table->timestamp('used_at')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamps();
        });

        Schema::create('addresses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->string('phone', 20);
            $table->string('division', 40);
            $table->string('district', 40);
            $table->string('area', 80);
            $table->string('line', 255);
            $table->boolean('is_default')->default(false);
            $table->timestamps();
        });

        Schema::create('wishlist_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('product_id', 40);
            $table->timestamps();
            $table->unique(['user_id', 'product_id']);
        });

        Schema::create('coupons', function (Blueprint $table) {
            $table->id();
            $table->string('code', 30)->unique();
            $table->string('type', 20); // percent_items | free_shipping | fixed
            $table->decimal('value', 10, 2)->default(0);
            $table->decimal('max_discount_bdt', 10, 2)->nullable();
            $table->decimal('min_subtotal_bdt', 10, 2)->default(0);
            $table->boolean('active')->default(true);
            $table->timestamp('expires_at')->nullable();
            $table->unsignedInteger('max_uses')->nullable();
            $table->unsignedInteger('used')->default(0);
            $table->timestamps();
        });

        Schema::create('orders', function (Blueprint $table) {
            $table->string('id', 12)->primary(); // SR-XXXXXX
            $table->foreignId('user_id')->constrained();
            $table->string('status', 20)->default('placed')->index();
            $table->string('payment_method', 20);
            $table->string('payment_status', 20)->default('pending'); // pending | paid | partial | failed | refunded
            $table->decimal('subtotal_bdt', 12, 2);
            $table->decimal('shipping_bdt', 12, 2);
            $table->decimal('discount_bdt', 12, 2)->default(0);
            $table->decimal('total_bdt', 12, 2);
            $table->decimal('paid_bdt', 12, 2)->default(0);
            $table->string('coupon_code', 30)->nullable();
            $table->json('address');
            $table->string('tracking_code', 20)->nullable()->unique();
            $table->string('skydrop_consignment_id')->nullable();
            $table->decimal('fx_rate', 10, 5);
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 12)->index();
            $table->string('product_id', 40);
            $table->string('market', 20);
            $table->string('title', 500);
            $table->string('image', 1000)->nullable();
            $table->json('placeholder')->nullable();
            $table->string('variant')->nullable();
            $table->unsignedSmallInteger('qty');
            $table->decimal('unit_bdt', 12, 2);
            $table->decimal('unit_inr', 12, 2);
            $table->decimal('weight_kg', 8, 3);
            $table->string('purchase_ref')->nullable(); // marketplace order id once bought
            $table->timestamps();
        });

        Schema::create('order_events', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 12)->index();
            $table->string('status', 20);
            $table->string('note')->nullable();
            $table->string('source', 20)->default('system'); // system | skydrop | admin | customer
            $table->timestamp('at');
        });

        Schema::create('order_verifications', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 12)->nullable()->index();
            $table->string('product_id', 40);
            $table->decimal('seen_bdt', 12, 2);
            $table->decimal('fresh_bdt', 12, 2)->nullable();
            $table->boolean('deliverable')->default(true);
            $table->boolean('ok');
            $table->string('provider', 30)->nullable();
            $table->json('raw')->nullable();
            $table->timestamp('verified_at');
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->string('order_id', 12)->index();
            $table->string('gateway', 20);
            $table->decimal('amount_bdt', 12, 2);
            $table->string('status', 20)->default('pending');
            $table->string('gateway_ref')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['payments', 'order_verifications', 'order_events', 'order_items', 'orders', 'coupons',
            'wishlist_items', 'addresses', 'otp_codes'] as $t) {
            Schema::dropIfExists($t);
        }
    }
};

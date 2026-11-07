<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Growth module: affiliate program (affiliates, clicks, commissions, payouts), refer-a-friend and shared wishlists.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('referral_code', 12)->nullable()->unique();  // ?invite=CODE
            $table->foreignId('referred_by')->nullable()->index();      // inviter user id
            $table->string('wishlist_code', 16)->nullable()->unique();  // public /w/{code}
        });

        Schema::table('orders', function (Blueprint $table) {
            $table->foreignId('affiliate_id')->nullable()->index();
            $table->string('ref_code', 20)->nullable();
        });

        Schema::create('affiliates', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique();
            $table->string('code', 20)->unique();                       // ?ref=CODE (uppercase)
            $table->timestamp('code_changed_at')->nullable();           // the code can be edited once
            $table->string('status', 20)->default('pending')->index();  // pending | approved | suspended
            $table->decimal('default_rate', 5, 2)->nullable();          // percent; overrides category rates when set
            $table->string('payout_method', 20)->nullable();            // bkash | nagad
            $table->string('payout_number', 20)->nullable();
            $table->text('notes')->nullable();                          // how they promote (applicant) / staff notes
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_clicks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->index();
            $table->string('landing_url', 500)->nullable();
            $table->string('product_id', 40)->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('ua', 255)->nullable();
            $table->timestamp('created_at')->nullable()->index();
        });

        Schema::create('affiliate_payouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->index();
            $table->decimal('amount_bdt', 12, 2);
            $table->string('method', 20);
            $table->string('number', 20)->nullable();
            $table->string('reference')->nullable();                    // bKash/Nagad transaction id
            $table->string('status', 20)->default('requested')->index(); // requested | paid | rejected
            $table->timestamp('paid_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();
        });

        Schema::create('affiliate_commissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('affiliate_id')->index();
            $table->string('order_id', 12)->unique();
            $table->decimal('base_bdt', 12, 2);                         // items after discounts, no shipping
            $table->decimal('rate', 5, 2);                              // blended percent
            $table->decimal('amount_bdt', 12, 2);
            $table->string('status', 20)->default('pending')->index();  // pending | approved | reversed | paid
            $table->timestamp('approve_after')->nullable();             // delivered_at + hold days
            $table->timestamp('approved_at')->nullable();
            $table->timestamp('reversed_at')->nullable();
            $table->timestamp('paid_at')->nullable();
            $table->foreignId('payout_id')->nullable()->index();
            $table->string('note')->nullable();
            $table->timestamps();
        });

        Schema::create('referral_rewards', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviter_id')->index();
            $table->foreignId('friend_id')->unique();                   // one reward per invited friend
            $table->string('order_id', 12);
            $table->unsignedInteger('inviter_points');
            $table->unsignedInteger('friend_points');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['referral_rewards', 'affiliate_commissions', 'affiliate_payouts', 'affiliate_clicks', 'affiliates'] as $t) {
            Schema::dropIfExists($t);
        }
        Schema::table('orders', function (Blueprint $table) {
            $table->dropIndex(['affiliate_id']);
            $table->dropColumn(['affiliate_id', 'ref_code']);
        });
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['referral_code']);
            $table->dropUnique(['wishlist_code']);
            $table->dropIndex(['referred_by']);
            $table->dropColumn(['referral_code', 'referred_by', 'wishlist_code']);
        });
    }
};

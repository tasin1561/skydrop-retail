<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Money module: store-credit wallet, gift cards, Skydrop Plus membership, card EMI (order columns).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('wallet_ledger', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('type', 10); // credit | debit
            $table->decimal('amount_bdt', 12, 2); // always positive; type gives the sign
            $table->string('reason', 20); // refund | gift_card | admin | promo | order_payment | expire
            $table->string('order_id', 12)->nullable()->index();
            $table->decimal('remaining_bdt', 12, 2)->default(0); // credits only: unspent part (FIFO lots)
            $table->timestamp('expires_at')->nullable();
            $table->decimal('balance_after_bdt', 12, 2)->default(0);
            $table->string('note', 255)->nullable();
            $table->json('meta')->nullable();
            $table->foreignId('staff_id')->nullable();
            $table->timestamps();
            $table->index(['user_id', 'type', 'remaining_bdt']);
        });

        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'wallet_balance_bdt')) {
                $table->decimal('wallet_balance_bdt', 12, 2)->default(0);
            }
        });

        Schema::create('gift_cards', function (Blueprint $table) {
            $table->id();
            $table->string('code', 16)->unique(); // stored without dashes, shown as XXXX-XXXX-XXXX-XXXX
            $table->decimal('initial_bdt', 12, 2);
            $table->decimal('balance_bdt', 12, 2);
            $table->foreignId('purchaser_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('recipient_name', 100)->nullable();
            $table->string('recipient_phone', 20)->nullable();
            $table->string('recipient_email', 190)->nullable();
            $table->string('message', 500)->nullable();
            $table->string('design', 30)->default('classic');
            $table->string('source', 20)->default('purchase'); // purchase | admin | promo
            $table->string('status', 20)->default('pending'); // pending (unpaid) | active | redeemed | void | expired
            $table->string('order_id', 12)->nullable()->index();
            $table->string('batch', 40)->nullable()->index();
            $table->timestamp('expires_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->foreignId('redeemed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('redeemed_at')->nullable();
            $table->string('void_reason', 255)->nullable();
            $table->timestamps();
        });

        Schema::create('membership_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name', 80);
            $table->string('slug', 80)->unique();
            $table->decimal('price_bdt', 12, 2);
            $table->string('period', 12)->default('monthly'); // monthly | quarterly | yearly
            $table->boolean('active')->default(true);
            $table->unsignedSmallInteger('sort')->default(0);
            $table->boolean('highlight')->default(false);
            $table->string('tagline', 160)->nullable();
            $table->json('benefits')->nullable();
            $table->timestamps();
        });

        Schema::create('memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->constrained('membership_plans');
            $table->string('status', 20)->default('active'); // active | expired | cancelled
            $table->timestamp('started_at')->nullable();
            $table->timestamp('renews_at')->nullable(); // paid-through date
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('last_credit_at')->nullable(); // monthly wallet credit
            $table->timestamp('reminded_at')->nullable();
            $table->json('payment_refs')->nullable(); // [{orderId, amountBdt, at, source}]
            $table->string('note', 255)->nullable();
            $table->timestamps();
            $table->index(['user_id', 'status']);
        });

        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'kind')) {
                $table->string('kind', 20)->default('goods')->index(); // goods | gift_card | membership
            }
            if (! Schema::hasColumn('orders', 'wallet_bdt')) {
                $table->decimal('wallet_bdt', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('orders', 'membership_discount_bdt')) {
                $table->decimal('membership_discount_bdt', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('orders', 'emi_charge_bdt')) {
                $table->decimal('emi_charge_bdt', 12, 2)->default(0);
            }
            if (! Schema::hasColumn('orders', 'emi')) {
                $table->json('emi')->nullable(); // {tenure, bank, ratePct, monthlyBdt}
            }
            if (! Schema::hasColumn('orders', 'meta')) {
                $table->json('meta')->nullable(); // gift_card_id / plan_id for non-goods orders
            }
        });
    }

    public function down(): void
    {
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn(['kind', 'wallet_bdt', 'membership_discount_bdt', 'emi_charge_bdt', 'emi', 'meta']));
        Schema::table('users', fn (Blueprint $t) => $t->dropColumn('wallet_balance_bdt'));
        Schema::dropIfExists('memberships');
        Schema::dropIfExists('membership_plans');
        Schema::dropIfExists('gift_cards');
        Schema::dropIfExists('wallet_ledger');
    }
};

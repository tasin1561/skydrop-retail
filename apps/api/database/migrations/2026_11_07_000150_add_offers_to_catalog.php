<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// Catalog: store offers (coupons, deals, bank offers) and the effective price we can buy at.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_details', function (Blueprint $table) {
            $table->json('offers')->nullable();
        });

        Schema::table('products', function (Blueprint $table) {
            $table->string('deal_badge')->nullable();
            $table->decimal('coupon_inr', 12, 2)->nullable();
            $table->decimal('effective_price_inr', 12, 2)->nullable();
            $table->timestamp('deal_ends_at')->nullable()->index();
            $table->decimal('saving_inr', 12, 2)->default(0)->index(); // MRP/list minus effective price, for "Live deals"
        });

        // A gzipped amazon.in page is ~400 KB; MySQL BLOB stops at 64 KB.
        if (in_array(DB::getDriverName(), ['mysql', 'mariadb'], true)) {
            DB::statement('ALTER TABLE raw_pages MODIFY html_gz LONGBLOB NOT NULL');
        }
    }

    public function down(): void
    {
        Schema::table('products', fn (Blueprint $t) => $t->dropColumn(['deal_badge', 'coupon_inr', 'effective_price_inr', 'deal_ends_at', 'saving_inr']));
        Schema::table('product_details', fn (Blueprint $t) => $t->dropColumn('offers'));
    }
};

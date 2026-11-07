<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Profit report inputs: what we really paid in India and at what FX rate.
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('order_items', function (Blueprint $table) {
            if (! Schema::hasColumn('order_items', 'actual_paid_inr')) {
                $table->decimal('actual_paid_inr', 12, 2)->nullable(); // line total paid on the source store (all units)
            }
        });
        Schema::table('orders', function (Blueprint $table) {
            if (! Schema::hasColumn('orders', 'fx_paid')) {
                $table->decimal('fx_paid', 10, 5)->nullable(); // BDT per INR we actually paid for this order
            }
        });
        Schema::create('fx_paid_rates', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->decimal('rate', 10, 5); // BDT per INR actually paid that day (hundi/bank/card)
            $table->string('note')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('fx_paid_rates');
        Schema::table('orders', fn (Blueprint $t) => $t->dropColumn('fx_paid'));
        Schema::table('order_items', fn (Blueprint $t) => $t->dropColumn('actual_paid_inr'));
    }
};

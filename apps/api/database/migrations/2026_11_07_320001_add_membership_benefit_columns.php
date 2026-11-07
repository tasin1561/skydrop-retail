<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Skydrop Plus benefits: priority support (support + return requests) and free returns (return shipping fee).
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('support_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('support_requests', 'priority')) {
                $table->boolean('priority')->default(false)->index(); // member with priority_support when opened
            }
        });
        Schema::table('return_requests', function (Blueprint $table) {
            if (! Schema::hasColumn('return_requests', 'priority')) {
                $table->boolean('priority')->default(false)->index();
            }
            if (! Schema::hasColumn('return_requests', 'return_fee_bdt')) {
                $table->decimal('return_fee_bdt', 12, 2)->default(0); // return shipping charged (deducted from the refund); 0 = free
            }
        });
    }

    public function down(): void
    {
        Schema::table('return_requests', fn (Blueprint $t) => $t->dropColumn(['priority', 'return_fee_bdt']));
        Schema::table('support_requests', fn (Blueprint $t) => $t->dropColumn('priority'));
    }
};

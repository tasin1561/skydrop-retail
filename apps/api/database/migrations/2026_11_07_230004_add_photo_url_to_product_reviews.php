<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('product_reviews', function (Blueprint $table) {
            $table->string('photo_url', 1000)->nullable()->after('body');
        });
    }

    public function down(): void
    {
        Schema::table('product_reviews', fn (Blueprint $table) => $table->dropColumn('photo_url'));
    }
};

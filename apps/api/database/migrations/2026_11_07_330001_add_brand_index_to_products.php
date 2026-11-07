<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Brand filter (GET /search?brand=, /products/popular?brand=) and per-category top brands group by brand within a market.
return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasIndex('products', ['market', 'brand'])) {
            Schema::table('products', fn (Blueprint $t) => $t->index(['market', 'brand']));
        }
    }

    public function down(): void
    {
        if (Schema::hasIndex('products', ['market', 'brand'])) {
            Schema::table('products', fn (Blueprint $t) => $t->dropIndex(['market', 'brand']));
        }
    }
};

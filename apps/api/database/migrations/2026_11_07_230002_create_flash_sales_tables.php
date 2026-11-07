<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CMS: flash sales. The extra discount is funded from our margin (capped at the margin by FlashSaleService).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('flash_sales', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('store', 20)->default('all')->index();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at')->index();
            $table->unsignedSmallInteger('max_qty')->nullable(); // per product, per order, at the flash price
            $table->boolean('active')->default(true);
            $table->timestamps();
        });

        Schema::create('flash_sale_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('flash_sale_id')->constrained('flash_sales')->cascadeOnDelete();
            $table->string('product_id', 40)->index();
            $table->decimal('off_pct', 5, 2)->nullable(); // extra % off the Skydrop price
            $table->unsignedInteger('off_bdt')->nullable(); // or a fixed BDT amount
            $table->integer('sort')->default(0);
            $table->timestamps();
            $table->unique(['flash_sale_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('flash_sale_items');
        Schema::dropIfExists('flash_sales');
    }
};

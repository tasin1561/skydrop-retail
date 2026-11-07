<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// CMS: home/store banners (hero carousel, promo strip, category tiles).
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('banners', function (Blueprint $table) {
            $table->id();
            $table->string('title', 160);
            $table->string('subtitle', 300)->nullable();
            $table->string('image_url', 1000)->nullable(); // absolute URL
            $table->string('image_path', 500)->nullable(); // upload on the public disk (wins over image_url)
            $table->string('link', 1000)->nullable();
            $table->string('cta', 40)->nullable();
            $table->string('store', 20)->default('all')->index(); // all | amazon | flipkart | bigbasket
            $table->string('placement', 20)->default('hero')->index(); // hero | strip | category
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->integer('sort')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('banners');
    }
};

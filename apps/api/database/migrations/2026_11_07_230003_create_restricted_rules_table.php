<?php

use Database\Seeders\RestrictedRuleSeeder;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Items that cannot (or may not) ship to Bangladesh. Defaults are seeded here so every environment has them.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('restricted_rules', function (Blueprint $table) {
            $table->id();
            $table->string('type', 20)->default('keyword'); // keyword | category | brand
            $table->string('pattern', 1000); // comma-separated words/phrases, or a /regex/i
            $table->string('action', 20)->default('warn'); // block | warn | surcharge
            $table->string('message', 500);
            $table->unsignedInteger('surcharge_bdt')->default(0); // per unit, for action=surcharge
            $table->boolean('active')->default(true);
            $table->integer('sort')->default(0);
            $table->timestamps();
        });

        (new RestrictedRuleSeeder)->run();
    }

    public function down(): void
    {
        Schema::dropIfExists('restricted_rules');
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Storefront now uses the stores' real names as plain text (no logos): Mega Store → Amazon, etc.
// Only rows still carrying the old default names are renamed, so an admin's custom name is kept.
return new class extends Migration
{
    private const MAP = [
        'amazon' => ['Mega Store', 'Amazon'],
        'flipkart' => ['Style Bazaar', 'Flipkart'],
        'bigbasket' => ['Daily Basket', 'BigBasket'],
    ];

    public function up(): void
    {
        foreach (self::MAP as $id => [$old, $new]) {
            DB::table('marketplaces')->where('id', $id)->where('name', $old)->update(['name' => $new]);
        }
    }

    public function down(): void
    {
        foreach (self::MAP as $id => [$old, $new]) {
            DB::table('marketplaces')->where('id', $id)->where('name', $new)->update(['name' => $old]);
        }
    }
};

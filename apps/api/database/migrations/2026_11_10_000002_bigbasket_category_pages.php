<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// BigBasket bestseller seeding now reads the store's category pages (its search pages no longer carry products).
// Only rows without a browse node are filled, so an admin's own value is kept.
return new class extends Migration
{
    private const PAGES = ['grocery' => '/cl/foodgrains-oil-masala/', 'home' => '/cl/cleaning-household/', 'beauty' => '/cl/beauty-hygiene/'];

    public function up(): void
    {
        foreach (self::PAGES as $slug => $page) {
            DB::table('categories')->where('market', 'bigbasket')->where('slug', $slug)
                ->where(fn ($q) => $q->whereNull('browse_node')->orWhere('browse_node', ''))->update(['browse_node' => $page]);
        }
    }

    public function down(): void
    {
        foreach (self::PAGES as $slug => $page) {
            DB::table('categories')->where('market', 'bigbasket')->where('slug', $slug)->where('browse_node', $page)->update(['browse_node' => null]);
        }
    }
};

<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

// BigBasket is no longer a store (owner's decision, Nov 2026). Past orders keep their BigBasket lines as they were; the
// store itself, its switches, seeding rows and category queries go, its products are hidden, and BigBasket-only
// coupons, banners and flash sales are switched off (not deleted, so their history stays readable).
return new class extends Migration
{
    public function up(): void
    {
        DB::table('marketplaces')->where('id', 'bigbasket')->delete();
        DB::table('settings')->where('key', 'features.store.bigbasket')->delete();
        DB::table('categories')->where('market', 'bigbasket')->delete();
        foreach (DB::table('categories')->where('market', 'all')->where('sources', 'like', '%bigbasket%')->get(['id', 'sources']) as $row) {
            $sources = json_decode((string) $row->sources, true) ?: [];
            unset($sources['bigbasket']);
            DB::table('categories')->where('id', $row->id)->update(['sources' => json_encode($sources)]);
        }
        DB::table('products')->where('market', 'bigbasket')->update(['hidden' => true]);
        DB::table('search_cache')->where('market', 'bigbasket')->delete();
        foreach (['coupons', 'banners', 'flash_sales'] as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'store')) {
                DB::table($table)->where('store', 'bigbasket')->update(['active' => false]);
            }
        }
    }

    public function down(): void
    {
        // The store is not restored automatically: run MarketplaceSeeder / CategoryTreeSeeder from a version that has it.
    }
};

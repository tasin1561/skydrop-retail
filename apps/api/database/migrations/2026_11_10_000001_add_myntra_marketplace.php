<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

// Myntra (myntra.com) as a fourth store. Existing installs get the marketplace row (catalog only until an admin turns
// on live data) and the "myntra" search query on the categories Myntra sells (from database/data/category_tree.json).
// Only categories without a Myntra entry are touched, so admin edits are kept.
return new class extends Migration
{
    public function up(): void
    {
        if (! DB::table('marketplaces')->where('id', 'myntra')->exists()) {
            DB::table('marketplaces')->insert([
                'id' => 'myntra', 'name' => 'Myntra', 'source' => 'myntra.com', 'enabled' => true, 'live_fetch' => false,
                'pincode' => config('retail.pincode', '700001'), 'budget_share' => 100, 'created_at' => now(), 'updated_at' => now(),
            ]);
        }

        $file = database_path('data/category_tree.json');
        if (! is_file($file)) {
            return;
        }
        $wanted = [];
        $walk = function (array $nodes) use (&$walk, &$wanted) {
            foreach ($nodes as $n) {
                if (isset($n['sources']['myntra'])) {
                    $wanted[$n['path']] = $n['sources']['myntra'];
                }
                $walk($n['children'] ?? []);
            }
        };
        $walk(json_decode((string) file_get_contents($file), true)['tree'] ?? []);

        foreach (DB::table('categories')->where('market', 'all')->whereIn('path', array_keys($wanted))->get(['id', 'path', 'sources']) as $row) {
            $sources = json_decode((string) $row->sources, true) ?: [];
            if (! isset($sources['myntra'])) {
                $sources['myntra'] = $wanted[$row->path];
                DB::table('categories')->where('id', $row->id)->update(['sources' => json_encode($sources)]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('categories')->where('market', 'all')->whereNotNull('sources')->get(['id', 'sources']) as $row) {
            $sources = json_decode((string) $row->sources, true) ?: [];
            if (isset($sources['myntra'])) {
                unset($sources['myntra']);
                DB::table('categories')->where('id', $row->id)->update(['sources' => json_encode($sources)]);
            }
        }
        DB::table('marketplaces')->where('id', 'myntra')->delete();
    }
};

<?php

namespace Database\Seeders;

use App\Services\Categories\CategoryTreeImporter;
use Illuminate\Database\Seeder;

/** Unified Skydrop Retail category tree from database/data/category_tree.json (idempotent by path). */
class CategoryTreeSeeder extends Seeder
{
    public function run(CategoryTreeImporter $importer): void
    {
        $s = $importer->import();
        $this->command?->getOutput()?->writeln("  Category tree: {$s['total']} nodes ({$s['created']} new, {$s['updated']} updated)");
    }
}

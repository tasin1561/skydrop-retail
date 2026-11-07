<?php

namespace Database\Seeders;

use App\Services\Categories\CategoryWriter;
use Illuminate\Database\Seeder;

/** Assigns seeded products to tree nodes (runs after CatalogSeeder; seeding mutes the Product observer). */
class CategorizeProductsSeeder extends Seeder
{
    public function run(CategoryWriter $writer): void
    {
        [$matched, $unmatched] = $writer->categorize();
        $writer->recount();
        $this->command?->getOutput()?->writeln("  Categorised $matched products ($unmatched unmatched)");
    }
}

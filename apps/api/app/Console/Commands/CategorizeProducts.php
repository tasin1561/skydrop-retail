<?php

namespace App\Console\Commands;

use App\Services\Categories\CategoryWriter;
use Illuminate\Console\Command;

class CategorizeProducts extends Command
{
    protected $signature = 'retail:categorize
        {--all : Re-match every product (manual assignments are kept)}
        {--force : With --all, also re-match manual assignments}
        {--recount : Only recompute the cached product counts}';

    protected $description = 'Assign products to the deepest matching category tree node (breadcrumbs, then title keywords)';

    public function handle(CategoryWriter $writer): int
    {
        if (! $this->option('recount')) {
            [$matched, $unmatched] = $writer->categorize((bool) $this->option('all'), (bool) $this->option('force'));
            $this->info("Matched $matched products, $unmatched left unmatched (see Admin → Categories → Unmatched products).");
        }
        $this->info('Updated product counts on '.$writer->recount().' categories.');

        return self::SUCCESS;
    }
}

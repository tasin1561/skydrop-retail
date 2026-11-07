<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Observers\ProductCategoryObserver;
use App\Services\Catalog\BudgetGuard;
use App\Services\Catalog\CatalogService;
use App\Services\Categories\CategoryTree;
use Illuminate\Console\Command;

/**
 * Warms the catalog for one tree node: for every leaf in the node's subtree that the store sells, queue the existing
 * catalog search fetch (CatalogService::queueSearch -> FetchSearchJob, one provider call per ~48-item store page) with
 * the node's store query. Listings are tagged with a "cat:{id}" hint so the Product observer files them under that
 * node. Default pages: enough for ~200 products per subcategory, spread over its leaves.
 */
class SeedCategory extends Command
{
    protected $signature = 'retail:seed-category
        {path : Tree path, e.g. mobiles-electronics/headphones-audio}
        {--store= : amazon|flipkart|bigbasket (default: every live store that sells it)}
        {--pages= : Store result pages per query (default: ~200 products per subcategory)}
        {--dry-run : List the queries without dispatching}';

    protected $description = 'Queue store searches for a category tree node (budget-guarded)';

    private const STORE_PAGE = 48;

    private const TARGET_PER_SUBCATEGORY = 200;

    public function handle(CategoryTree $tree, CatalogService $catalog, BudgetGuard $budget): int
    {
        $node = $tree->byPath((string) $this->argument('path'));
        if (! $node) {
            $this->error('Unknown category path.');

            return self::FAILURE;
        }
        $stores = $this->option('store') ? [(string) $this->option('store')] : Category::STORES;
        $leaves = array_values(array_filter(array_map(fn ($id) => $tree->find($id), $tree->subtree($node['id'])), fn ($n) => ! $n['children']));
        $subcats = max(1, count(array_filter(array_map(fn ($id) => $tree->find($id), $tree->subtree($node['id'])), fn ($n) => $n['children'])));
        $pages = $this->option('pages') !== null ? max(1, (int) $this->option('pages'))
            : max(1, (int) ceil(self::TARGET_PER_SUBCATEGORY * $subcats / self::STORE_PAGE / max(1, count($leaves))));

        $queued = 0;
        foreach ($stores as $store) {
            $plans = array_values(array_filter($leaves, fn ($l) => ! empty($l['sources'][$store]['query'])));
            if (! $plans) {
                continue;
            }
            if (! $this->option('dry-run') && ! $catalog->isLive($store)) {
                $this->warn("$store is not live (live_fetch off): skipped.");

                continue;
            }
            foreach ($plans as $leaf) {
                $q = (string) $leaf['sources'][$store]['query'];
                for ($page = 1; $page <= $pages; $page++) {
                    if ($this->option('dry-run')) {
                        $this->line("$store  p$page  \"$q\"  → {$leaf['path']}");

                        continue;
                    }
                    if (! $budget->allowsBackground()) {
                        $this->warn("Oxylabs budget above 90%: stopped after $queued searches.");

                        return self::SUCCESS;
                    }
                    $catalog->queueSearch($store, $q, $page, 'relevance', ProductCategoryObserver::HINT.$leaf['id']);
                    $queued++;
                }
            }
        }
        $this->info($this->option('dry-run') ? 'Dry run: nothing queued.' : "Queued $queued store searches ($pages page(s) per query) for {$node['path']}.");

        return self::SUCCESS;
    }
}

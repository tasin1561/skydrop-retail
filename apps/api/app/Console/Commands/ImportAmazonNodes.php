<?php

namespace App\Console\Commands;

use App\Models\Category;
use App\Services\Catalog\BudgetGuard;
use App\Services\Categories\CategoryTree;
use App\Sources\Support\OxylabsClient;
use Illuminate\Console\Command;
use Throwable;

/**
 * Discovers real amazon.in browse node ids for tree nodes with Oxylabs `amazon_bestsellers` and stores them in
 * sources.amazon.node (the tree JSON deliberately ships with node = null; ids are never invented).
 *
 * Needs a live Oxylabs token (retail.oxylabs.token) and costs one request per node; budget-guarded.
 */
class ImportAmazonNodes extends Command
{
    protected $signature = 'retail:import-amazon-nodes
        {path? : Limit to this subtree}
        {--depth=2 : Deepest level to look up}
        {--dry-run : Show what would be requested}';

    protected $description = 'Fill sources.amazon.node from amazon.in bestseller pages (needs a live Oxylabs token)';

    public function handle(CategoryTree $tree, BudgetGuard $budget): int
    {
        $root = $this->argument('path') ? $tree->byPath((string) $this->argument('path')) : null;
        $ids = $root ? $tree->subtree($root['id']) : array_keys($tree->nodes());
        $targets = array_values(array_filter(array_map(fn ($id) => $tree->find($id), $ids),
            fn ($n) => $n['depth'] <= (int) $this->option('depth') && isset($n['sources']['amazon']) && empty($n['sources']['amazon']['node'])));
        $this->info(count($targets).' nodes without an amazon.in browse node id.');
        if ($this->option('dry-run')) {
            foreach ($targets as $n) {
                $this->line("{$n['path']}  ← amazon_bestsellers query \"{$n['sources']['amazon']['search_alias']}\"");
            }

            return self::SUCCESS;
        }
        if (! config('retail.oxylabs.token')) {
            $this->warn('No Oxylabs token configured (retail.oxylabs.token): nothing requested.');

            return self::SUCCESS;
        }
        $client = app(OxylabsClient::class);
        foreach ($targets as $n) {
            if (! $budget->allowsBackground()) {
                $this->warn('Budget guard: stopping.');
                break;
            }
            try {
                // TODO(live token): confirm the parsed shape of amazon_bestsellers for a department page. Oxylabs returns
                // the page `url` (…/gp/bestsellers/{alias}/{nodeId}) and, for parsed pages, a category list we can
                // match by name. Resolve the node id from the URL / matching child name, then store it.
                $r = $client->query(['source' => 'amazon_bestsellers', 'domain' => 'in', 'query' => $n['sources']['amazon']['search_alias'], 'parse' => true],
                    'bestsellers', 'amazon', 'nodes:'.$n['path']);
                $nodeId = self::nodeFromUrl((string) data_get($r, 'content.url', ''));
                if ($nodeId) {
                    $cat = Category::query()->find($n['id']);
                    $src = (array) $cat->sources;
                    $src['amazon']['node'] = $nodeId;
                    $cat->update(['sources' => $src]);
                    $this->line("{$n['path']} → $nodeId");
                } else {
                    $this->line("{$n['path']}: no node id in the response (TODO: match child category names).");
                }
            } catch (Throwable $e) {
                $this->warn("{$n['path']}: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }

    public static function nodeFromUrl(string $url): ?string
    {
        return preg_match('~/(?:gp/bestsellers|zgbs)/[a-z-]+/(\d{4,})~', $url, $m) ? $m[1] : null;
    }
}

<?php

namespace App\Filament\Resources\Categories;

use App\Filament\Support\Admin;
use App\Models\Category;
use Filament\Notifications\Notification;
use Illuminate\Support\Facades\Artisan;
use Throwable;

/** Shared helpers for the category tree admin (resource table, tree page, unmatched products). */
class CategoryAdmin
{
    public const AMAZON_ALIASES = ['aps', 'electronics', 'computers', 'fashion', 'shoes', 'beauty', 'hpc', 'grocery', 'kitchen', 'appliances',
        'baby', 'sporting', 'stripbooks', 'toys', 'automotive', 'luggage', 'jewelry', 'watches', 'office-products', 'pets'];

    /** Icon keys from apps/web/src/components/Icon.tsx usable for categories. */
    public const ICONS = ['phone', 'charger', 'audio', 'watch', 'tv', 'camera', 'game', 'wifi', 'laptop', 'tablet', 'storage', 'mouse',
        'printer', 'bag', 'shirt', 'dress', 'shoe', 'wallet', 'gem', 'luggage', 'glasses', 'backpack', 'beauty', 'smile', 'hair', 'perfume',
        'scissors', 'spray', 'tooth', 'heart', 'heartbeat', 'pill', 'leaf', 'dumbbell', 'home', 'pot', 'frame', 'bed', 'sofa', 'lamp',
        'package', 'paw', 'tool', 'plug', 'fan', 'fridge', 'basket', 'grain', 'spice', 'juice', 'tea', 'noodle', 'choco', 'milk', 'star',
        'oil', 'nut', 'baby', 'ball', 'cycle', 'tent', 'book', 'pen', 'music', 'blocks', 'car', 'bike', 'gift', 'grid', 'globe', 'bolt', 'spark'];

    /** @return array<string, string> markets for the market select/filter */
    public static function markets(): array
    {
        return [Category::UNIFIED => 'Unified tree'] + Admin::MARKETS;
    }

    /** @return array<int, string> id => indented path label of tree nodes */
    public static function nodeOptions(bool $parentsOnly = false, ?int $except = null): array
    {
        return Category::query()->tree()->orderBy('sort_key')
            ->when($parentsOnly, fn ($q) => $q->where('depth', '<', 4))
            ->when($except, fn ($q) => $q->whereKeyNot($except))
            ->get(['id', 'path', 'name', 'depth'])
            ->mapWithKeys(fn (Category $c) => [$c->id => str_repeat('· ', max(0, $c->depth - 1)).$c->name.'  ('.$c->path.')'])
            ->all();
    }

    /**
     * Form state → stored sources: a store whose query is empty does not sell this category.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function cleanSources(array $data): array
    {
        if (($data['market'] ?? Category::UNIFIED) !== Category::UNIFIED) {
            return $data;
        }
        $src = (array) ($data['sources'] ?? []);
        $out = [];
        foreach (Category::STORES as $store) {
            $s = array_filter((array) ($src[$store] ?? []), fn ($v) => $v !== null && $v !== '');
            if (! empty($s['query'])) {
                $out[$store] = $store === 'amazon' ? $s + ['search_alias' => 'aps', 'node' => null] : $s;
            }
        }
        $data['sources'] = $out;

        return $data;
    }

    public static function seed(Category $c, ?string $store, ?int $pages): void
    {
        try {
            $args = ['path' => $c->path] + ($store ? ['--store' => $store] : []) + ($pages ? ['--pages' => $pages] : []);
            Artisan::call('retail:seed-category', $args);
            Notification::make()->title('Seeding '.$c->name)->body(trim(Artisan::output()) ?: 'Queued.')->success()->send();
        } catch (Throwable $e) {
            Notification::make()->title('Could not seed')->body($e->getMessage())->danger()->send();
        }
    }

    public static function recount(): void
    {
        Artisan::call('retail:categorize', ['--recount' => true]);
        Notification::make()->title('Product counts updated')->body(trim(Artisan::output()))->success()->send();
    }
}

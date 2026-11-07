<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Services\Categories\CategoryWriter;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Support\Icons\Heroicon;

/**
 * Nested tree manager: expand/collapse, drag to reorder among siblings (native HTML drag and drop, no package),
 * up/down buttons as the keyboard-friendly fallback. Moving to another parent is the "Move to…" action on the row
 * or edit page (it recomputes every path below).
 */
class CategoryTreePage extends Page
{
    protected static string $resource = CategoryResource::class;

    protected string $view = 'filament.categories.tree';

    protected static ?string $title = 'Category tree';

    /** @return list<array<string, mixed>> */
    public function getTree(): array
    {
        $rows = Category::query()->tree()->orderBy('sort_key')->get(['id', 'parent_id', 'name', 'name_bn', 'path', 'icon', 'depth', 'active', 'featured', 'product_count', 'sources']);
        $by = [];
        foreach ($rows as $r) {
            $by[$r->parent_id ?? 0][] = $r;
        }
        $build = function (int $parent) use (&$build, $by): array {
            return array_map(fn (Category $c) => [
                'id' => $c->id, 'name' => $c->name, 'name_bn' => $c->name_bn, 'path' => $c->path, 'icon' => $c->icon, 'active' => $c->active,
                'featured' => $c->featured, 'count' => $c->product_count, 'stores' => $c->stores(), 'children' => $build($c->id),
                'edit' => CategoryResource::getUrl('edit', ['record' => $c]),
            ], $by[$parent] ?? []);
        };

        return $build(0);
    }

    public function shift(int $id, int $dir): void
    {
        if ($c = Category::query()->tree()->find($id)) {
            app(CategoryWriter::class)->shift($c, $dir);
        }
    }

    /** @param list<int|string> $ids siblings in their new order */
    public function reorder(array $ids): void
    {
        $ids = array_map('intval', $ids);
        $parents = Category::query()->tree()->whereIn('id', $ids)->distinct()->pluck('parent_id');
        if ($parents->count() !== 1) {
            Notification::make()->title('Drag within the same parent; use "Move to…" to change the parent.')->warning()->send();

            return;
        }
        app(CategoryWriter::class)->reorder($ids);
        Notification::make()->title('Order saved')->success()->send();
    }

    protected function getHeaderActions(): array
    {
        return [
            Action::make('list')->label('Table view')->icon(Heroicon::OutlinedTableCells)->color('gray')->url(CategoryResource::getUrl('index')),
            Action::make('create')->label('New category')->url(CategoryResource::getUrl('create')),
        ];
    }
}

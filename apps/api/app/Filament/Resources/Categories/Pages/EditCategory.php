<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryAdmin;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Resources\Categories\Tables\CategoriesTable;
use App\Models\Category;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

/** Saving recomputes path/depth/order for the node and its subtree (Category model events) and busts the tree cache. */
class EditCategory extends EditRecord
{
    protected static string $resource = CategoryResource::class;

    protected function mutateFormDataBeforeSave(array $data): array
    {
        return CategoryAdmin::cleanSources($data);
    }

    protected function getHeaderActions(): array
    {
        $tree = fn () => $this->getRecord()->market === Category::UNIFIED;

        return [
            CategoriesTable::moveAction()->visible($tree)->after(fn () => $this->refreshFormData(['parent_id', 'path'])),
            CategoriesTable::seedAction()->visible($tree),
            DeleteAction::make()->modalDescription('Children move up one level; products in this node become unmatched.'),
        ];
    }
}

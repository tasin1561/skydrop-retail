<?php

namespace App\Filament\Resources\Categories\Tables;

use App\Filament\Resources\Categories\CategoryAdmin;
use App\Filament\Support\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Services\Categories\CategoryWriter;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;
use InvalidArgumentException;

/** Tree nodes in tree order with indentation; filter by parent/level; move, reorder, seed and recount actions. */
class CategoriesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('sort_key')
            ->paginated([50, 100, 250])
            ->defaultPaginationPageOption(100)
            ->columns([
                TextColumn::make('name')->searchable(['name', 'name_bn', 'path'])->weight('bold')
                    ->formatStateUsing(fn (string $state, Category $r) => $r->market === Category::UNIFIED ? str_repeat('—  ', max(0, $r->depth - 1)).$state : $state)
                    ->description(fn (Category $r) => $r->market === Category::UNIFIED ? $r->path : $r->slug),
                TextColumn::make('name_bn')->label('বাংলা')->toggleable(),
                TextColumn::make('market')->badge()->formatStateUsing(fn ($state) => CategoryAdmin::markets()[$state] ?? $state)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('icon')->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('pricing_group')->label('Pricing')->badge()->placeholder('inherit')->toggleable(),
                TextColumn::make('stores')->label('Stores')->badge()
                    ->state(fn (Category $r) => $r->market === Category::UNIFIED ? array_map(fn ($s) => Admin::marketLabel($s), $r->stores()) : [Admin::marketLabel($r->market)]),
                TextColumn::make('product_count')->label('Products')->numeric()->sortable()
                    ->state(fn (Category $r) => $r->market === Category::UNIFIED ? $r->product_count
                        : Product::query()->where('market', $r->market)->where('category', $r->slug)->count()),
                ToggleColumn::make('featured'),
                ToggleColumn::make('active'),
            ])
            ->filters([
                SelectFilter::make('market')->options(CategoryAdmin::markets())->default(Category::UNIFIED),
                SelectFilter::make('parent_id')->label('Inside')->options(fn () => CategoryAdmin::nodeOptions(true))->searchable()
                    ->query(function ($query, array $data) {
                        if (! $data['value'] || ! ($p = Category::query()->find($data['value']))) {
                            return $query;
                        }

                        return $query->where('path', 'like', addcslashes((string) $p->path, '%_\\').'/%');
                    }),
                SelectFilter::make('depth')->label('Level')->options([1 => 'Top level', 2 => 'Level 2', 3 => 'Level 3', 4 => 'Level 4']),
                TernaryFilter::make('featured'),
                TernaryFilter::make('active'),
            ])
            ->recordActions([
                Action::make('up')->label('')->tooltip('Move up')->icon(Heroicon::OutlinedArrowUp)->color('gray')
                    ->visible(fn (Category $r) => $r->market === Category::UNIFIED)
                    ->action(fn (Category $record) => app(CategoryWriter::class)->shift($record, -1)),
                Action::make('down')->label('')->tooltip('Move down')->icon(Heroicon::OutlinedArrowDown)->color('gray')
                    ->visible(fn (Category $r) => $r->market === Category::UNIFIED)
                    ->action(fn (Category $record) => app(CategoryWriter::class)->shift($record, 1)),
                EditAction::make(),
                ActionGroup::make([
                    self::moveAction(),
                    self::seedAction(),
                    Action::make('recount')->label('Recount products')->icon(Heroicon::OutlinedCalculator)
                        ->action(fn () => CategoryAdmin::recount()),
                    Action::make('browse')->label('Open on the storefront')->icon(Heroicon::OutlinedArrowTopRightOnSquare)
                        ->visible(fn (Category $r) => $r->market === Category::UNIFIED)
                        ->url(fn (Category $r) => rtrim((string) config('retail.web_url', config('app.frontend_url', '')), '/').'/c/'.$r->path, true),
                ])->visible(fn (Category $r) => $r->market === Category::UNIFIED),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('activate')->label('Activate')->icon(Heroicon::OutlinedEye)
                        ->action(fn (Collection $records) => $records->each->update(['active' => true])),
                    BulkAction::make('deactivate')->label('Deactivate')->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => $records->each->update(['active' => false])),
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function moveAction(): Action
    {
        return Action::make('move')->label('Move to…')->icon(Heroicon::OutlinedArrowsRightLeft)
            ->schema(fn (Category $record) => [
                Select::make('parent_id')->label('New parent')->placeholder('Top level')->searchable()
                    ->options(array_filter(CategoryAdmin::nodeOptions(true, $record->id), fn ($label) => ! str_contains($label, '('.$record->path.'/'))),
            ])
            ->action(function (Category $record, array $data) {
                try {
                    $moved = app(CategoryWriter::class)->move($record, $data['parent_id'] ? (int) $data['parent_id'] : null);
                    Notification::make()->title('Moved to '.$moved->path)->success()->send();
                } catch (InvalidArgumentException $e) {
                    Notification::make()->title($e->getMessage())->danger()->send();
                }
            });
    }

    public static function seedAction(): Action
    {
        return Action::make('seed')->label('Seed this category')->icon(Heroicon::OutlinedCloudArrowDown)
            ->visible(fn () => Admin::can('categories.seed'))
            ->modalDescription('Queues store searches for every subcategory (about 200 products per subcategory). Each store page is one paid provider call; stops at 90% of the monthly budget.')
            ->schema(fn (Category $record) => [
                Select::make('store')->placeholder('Every store that sells it')->options(array_intersect_key(Admin::MARKETS, array_flip($record->stores()))),
                TextInput::make('pages')->label('Pages per query')->numeric()->minValue(1)->maxValue(10)->placeholder('auto'),
            ])
            ->action(fn (Category $record, array $data) => CategoryAdmin::seed($record, $data['store'] ?? null, isset($data['pages']) ? (int) $data['pages'] : null));
    }
}

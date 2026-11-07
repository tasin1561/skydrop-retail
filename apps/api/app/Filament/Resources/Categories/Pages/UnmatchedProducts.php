<?php

namespace App\Filament\Resources\Categories\Pages;

use App\Filament\Resources\Categories\CategoryAdmin;
use App\Filament\Resources\Categories\CategoryResource;
use App\Filament\Support\Admin;
use App\Models\Category;
use App\Models\Product;
use App\Services\Categories\CategoryWriter;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

/** Products the matcher could not place (category_id null), with manual bulk assignment. */
class UnmatchedProducts extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $resource = CategoryResource::class;

    protected static ?string $title = 'Unmatched products';

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(Product::query()->whereNull('category_id'))
            ->description('Manual assignments are kept when the matcher runs again. Add keywords to a category to match similar products automatically.')
            ->defaultSort('reviews', 'desc')
            ->columns([
                ImageColumn::make('image')->label('')->imageSize(40)->extraImgAttributes(['loading' => 'lazy', 'style' => 'object-fit:contain;background:#fff']),
                TextColumn::make('title')->limit(80)->wrap()->searchable()->description(fn (Product $r) => $r->id)
                    ->url(fn (Product $r) => Admin::sourceUrl($r->market, $r->id), true),
                TextColumn::make('market')->badge()->formatStateUsing(fn ($state) => Admin::marketLabel($state)),
                TextColumn::make('category')->label('Pricing')->badge()->color('gray'),
                TextColumn::make('brand')->toggleable(),
                TextColumn::make('reviews')->numeric()->sortable(),
            ])
            ->filters([SelectFilter::make('market')->options(Admin::MARKETS), SelectFilter::make('category')->label('Pricing group')
                ->options(array_combine(Category::PRICING_GROUPS, array_map('ucfirst', Category::PRICING_GROUPS)))])
            ->recordActions([
                Action::make('assign')->label('Assign')->icon(Heroicon::OutlinedFolderPlus)
                    ->schema([self::nodeSelect()])
                    ->action(fn (Product $record, array $data) => $this->assign([$record->id], (int) $data['category_id'])),
            ])
            ->toolbarActions([
                BulkAction::make('assign')->label('Assign category')->icon(Heroicon::OutlinedFolderPlus)
                    ->schema([self::nodeSelect()])
                    ->action(fn (Collection $records, array $data) => $this->assign($records->pluck('id')->all(), (int) $data['category_id'])),
                BulkAction::make('auto')->label('Try the matcher again')->icon(Heroicon::OutlinedSparkles)
                    ->action(function (Collection $records) {
                        [$m, $u] = app(CategoryWriter::class)->categorize(ids: $records->pluck('id')->all());
                        Notification::make()->title("Matched $m, $u still unmatched")->success()->send();
                    }),
            ]);
    }

    private static function nodeSelect(): Select
    {
        return Select::make('category_id')->label('Category')->required()->searchable()->options(fn () => CategoryAdmin::nodeOptions());
    }

    /** @param list<string> $ids */
    private function assign(array $ids, int $categoryId): void
    {
        $node = Category::query()->tree()->findOrFail($categoryId);
        $n = app(CategoryWriter::class)->assign($ids, $node);
        Notification::make()->title("$n product(s) filed under {$node->path}")->success()->send();
    }
}

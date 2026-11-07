<?php

namespace App\Filament\Resources\Products\Tables;

use App\Filament\Support\Admin;
use App\Filament\Support\Ops;
use App\Models\Category;
use App\Models\Product;
use App\Services\PricingService;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ProductsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('image')->label('')->imageSize(44)->extraImgAttributes(['loading' => 'lazy', 'style' => 'object-fit:contain;background:#fff']),
                TextColumn::make('title')->searchable()->limit(60)->tooltip(fn (Product $r) => $r->title)->wrap()
                    ->description(fn (Product $r) => $r->id.($r->brand ? ' · '.$r->brand : '')),
                TextColumn::make('id')->label('Id')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('market')->badge()->sortable()
                    ->formatStateUsing(fn ($state) => ['amazon' => 'Mega Store', 'flipkart' => 'Style Bazaar', 'bigbasket' => 'Daily Basket'][$state] ?? $state),
                TextColumn::make('category')->sortable()->toggleable(),
                TextColumn::make('price_inr')->label('Price ₹')->numeric()->sortable(),
                TextColumn::make('price_bdt')->label('Price ৳')
                    ->state(fn (Product $r) => app(PricingService::class)->priceBdt($r->price_inr, $r->category))->numeric(),
                TextColumn::make('rating')->numeric(1)->sortable()->toggleable(),
                IconColumn::make('in_stock')->boolean()->label('Stock'),
                IconColumn::make('detailed')->boolean()->toggleable(),
                ToggleColumn::make('hidden'),
                TextColumn::make('price_checked_at')->label('Price checked')->since()->sortable()->placeholder('never'),
                TextColumn::make('source')->badge()->color('gray')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('views')->numeric()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('market')->options(Admin::MARKETS),
                SelectFilter::make('category')
                    ->options(fn () => Product::query()->distinct()->orderBy('category')->pluck('category', 'category')->all()
                        + Category::query()->orderBy('slug')->pluck('name', 'slug')->all())
                    ->searchable(),
                TernaryFilter::make('hidden'),
                TernaryFilter::make('in_stock'),
                TernaryFilter::make('detailed'),
                TernaryFilter::make('demo'),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                self::refreshAction(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('hide')->label('Hide')->icon(Heroicon::OutlinedEyeSlash)
                        ->action(fn (Collection $records) => $records->each->update(['hidden' => true])),
                    BulkAction::make('unhide')->label('Unhide')->icon(Heroicon::OutlinedEye)
                        ->action(fn (Collection $records) => $records->each->update(['hidden' => false])),
                    BulkAction::make('refresh')->label('Refresh from source')->icon(Heroicon::OutlinedArrowPath)
                        ->requiresConfirmation()->modalDescription('Each product is one paid provider call.')
                        ->action(function (Collection $records) {
                            $n = $records->filter(fn (Product $p) => Ops::refreshProduct($p->id))->count();
                            self::notifyRefresh($n > 0, $n.' product(s) queued');
                        }),
                ]),
            ]);
    }

    public static function refreshAction(): Action
    {
        return Action::make('refresh')
            ->label('Refresh from source')
            ->icon(Heroicon::OutlinedArrowPath)
            ->requiresConfirmation()
            ->modalDescription('Queues a live fetch of this product and its variants. This is a paid provider call.')
            ->action(fn (Product $record) => self::notifyRefresh(Ops::refreshProduct($record->id), 'Refresh queued for '.$record->id));
    }

    private static function notifyRefresh(bool $ok, string $title): void
    {
        $ok
            ? Notification::make()->title($title)->success()->send()
            : Notification::make()->title('Catalog fetch job is not available yet')->body('App\Jobs\Catalog\FetchProductJob is part of the catalog module.')->warning()->send();
    }
}

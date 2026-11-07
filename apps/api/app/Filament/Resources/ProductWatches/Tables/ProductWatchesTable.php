<?php

namespace App\Filament\Resources\ProductWatches\Tables;

use App\Filament\Resources\Products\ProductResource;
use App\Filament\Support\Admin;
use App\Models\Product;
use App\Models\ProductWatch;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class ProductWatchesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('product_id')->label('Product')->searchable()
                    ->description(fn (ProductWatch $r) => str(Product::query()->whereKey($r->product_id)->value('title'))->limit(50))
                    ->url(fn (ProductWatch $r) => ProductResource::getUrl('view', ['record' => $r->product_id])),
                TextColumn::make('user.name')->label('Customer')->description(fn (ProductWatch $r) => $r->user?->phone),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => ['price_drop' => 'Price drop', 'back_in_stock' => 'Back in stock'][$state] ?? $state),
                TextColumn::make('baseline_bdt')->label('Baseline')->formatStateUsing(fn ($state) => $state ? Admin::bdt($state) : '—'),
                TextColumn::make('target_bdt')->label('Target')->formatStateUsing(fn ($state) => $state ? Admin::bdt($state) : '—'),
                IconColumn::make('active')->boolean(),
                TextColumn::make('notified_at')->since()->placeholder('not yet'),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('type')->options(['price_drop' => 'Price drop', 'back_in_stock' => 'Back in stock']),
                TernaryFilter::make('active'),
            ]);
    }
}

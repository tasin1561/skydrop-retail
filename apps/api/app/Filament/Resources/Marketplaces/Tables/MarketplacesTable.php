<?php

namespace App\Filament\Resources\Marketplaces\Tables;

use App\Filament\Support\Admin;
use App\Models\ApiLog;
use App\Models\Marketplace;
use App\Models\Product;
use Filament\Actions\EditAction;
use Filament\Notifications\Notification;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Table;

class MarketplacesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description('Turning a store OFF hides it for all shoppers immediately. Live fetch controls paid provider calls.')
            ->columns([
                TextColumn::make('name')->label('Store')->weight('bold')->description(fn (Marketplace $r) => $r->source),
                ToggleColumn::make('enabled')->label('ON / OFF')->onColor('success')->offColor('danger')
                    ->disabled(fn () => ! Admin::can('marketplaces.toggle'))
                    ->afterStateUpdated(fn (Marketplace $r, $state) => Notification::make()
                        ->title($r->name.($state ? ' is now ON for shoppers' : ' is now OFF and hidden from all shoppers'))
                        ->{$state ? 'success' : 'warning'}()->send()),
                ToggleColumn::make('live_fetch')->label('Live fetch')
                    ->disabled(fn () => ! Admin::can('marketplaces.toggle')),
                TextColumn::make('pincode'),
                TextColumn::make('budget_share')->label('Budget share')->suffix('%'),
                TextColumn::make('products_count')->label('Products')
                    ->state(fn (Marketplace $r) => Product::query()->where('market', $r->id)->count())->numeric(),
                TextColumn::make('spend_month')->label('Spend this month')
                    ->state(fn (Marketplace $r) => (float) ApiLog::query()->where('market', $r->id)
                        ->where('created_at', '>=', now()->startOfMonth())->sum('cost_usd'))
                    ->money('USD', 2),
                TextColumn::make('updated_at')->since()->label('Changed'),
            ])
            ->paginated(false)
            ->recordActions([
                EditAction::make(),
            ]);
    }
}

<?php

namespace App\Filament\Resources\PricingRules\Tables;

use App\Filament\Support\Admin;
use App\Models\PricingRule;
use App\Services\PricingService;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class PricingRulesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->description('Prices are cached for 10 minutes; saving a rule clears that cache.')
            ->columns([
                TextColumn::make('category')->weight('bold')->formatStateUsing(fn ($state) => $state === '*' ? '* (default)' : $state)->searchable(),
                TextColumn::make('margin_pct')->label('Margin')->suffix('%')->sortable()
                    ->color(fn ($state) => $state < 5 ? 'danger' : ($state < 8 ? 'warning' : null)),
                TextColumn::make('import_pct')->label('Import')->suffix('%')->sortable(),
                TextColumn::make('default_weight_kg')->label('Default weight')->suffix(' kg'),
                TextColumn::make('example')->label('₹1,000 sells for')
                    ->state(function (PricingRule $r) {
                        Cache::forget('pricing:rules');

                        return Admin::bdt(app(PricingService::class)->priceBdt(1000, $r->category));
                    }),
                TextColumn::make('updated_at')->since(),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()->visible(fn () => Admin::canManage())]),
            ]);
    }
}

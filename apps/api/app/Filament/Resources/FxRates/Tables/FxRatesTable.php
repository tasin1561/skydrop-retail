<?php

namespace App\Filament\Resources\FxRates\Tables;

use App\Filament\Support\Admin;
use App\Models\FxRate;
use Filament\Actions\DeleteAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Cache;

class FxRatesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('fetched_at', 'desc')
            ->columns([
                TextColumn::make('pair'),
                TextColumn::make('rate')->numeric(5)->weight('bold'),
                TextColumn::make('source')->badge()->color(fn ($state) => $state === 'manual' ? 'warning' : 'info'),
                TextColumn::make('fetched_at')->label('Effective')->dateTime()->sortable()
                    ->description(fn (FxRate $r) => $r->id === FxRate::query()->latest('fetched_at')->value('id') ? 'In use' : null),
            ])
            ->filters([
                SelectFilter::make('source')->options(fn () => FxRate::query()->distinct()->pluck('source', 'source')->all()),
            ])
            ->recordActions([
                DeleteAction::make()->visible(fn () => Admin::canManage())->after(fn () => Cache::forget('fx:INRBDT')),
            ]);
    }
}

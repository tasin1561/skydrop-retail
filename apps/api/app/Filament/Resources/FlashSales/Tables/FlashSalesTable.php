<?php

namespace App\Filament\Resources\FlashSales\Tables;

use App\Filament\Resources\Banners\Schemas\BannerForm;
use App\Filament\Support\Admin;
use App\Models\FlashSale;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class FlashSalesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('starts_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->withCount('items'))
            ->columns([
                TextColumn::make('title')->searchable()->weight('bold'),
                TextColumn::make('status')->state(fn (FlashSale $r) => $r->status())->badge()
                    ->color(fn ($state) => ['live' => 'success', 'upcoming' => 'warning', 'ended' => 'gray', 'off' => 'danger'][$state] ?? 'gray'),
                TextColumn::make('store')->badge()->formatStateUsing(fn ($state) => BannerForm::stores()[$state] ?? $state),
                TextColumn::make('starts_at')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('ends_at')->dateTime('d M Y H:i')->sortable(),
                TextColumn::make('items_count')->label('Products'),
                TextColumn::make('max_qty')->label('Max/order')->placeholder('—'),
                ToggleColumn::make('active'),
            ])
            ->filters([TernaryFilter::make('active')])
            ->recordActions([EditAction::make()])
            ->toolbarActions([BulkActionGroup::make([DeleteBulkAction::make()->visible(fn () => Admin::canManage())])]);
    }
}

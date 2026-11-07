<?php

namespace App\Filament\Resources\Users\RelationManagers;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use Filament\Actions\ViewAction;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class OrdersRelationManager extends RelationManager
{
    protected static string $relationship = 'orders';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->weight('bold'),
                TextColumn::make('created_at')->dateTime('d M Y'),
                TextColumn::make('status')->badge()
                    ->formatStateUsing(fn ($state) => Admin::ORDER_STATUS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => Admin::ORDER_STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('payment_status')->badge()->color(fn ($state) => Admin::PAYMENT_STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('total_bdt')->label('Total')->formatStateUsing(fn ($state) => Admin::bdt($state)),
            ])
            ->recordActions([
                ViewAction::make()->url(fn ($record) => OrderResource::getUrl('view', ['record' => $record])),
            ]);
    }
}

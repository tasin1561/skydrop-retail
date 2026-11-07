<?php

namespace App\Filament\Resources\Coupons\Tables;

use App\Filament\Resources\Coupons\Schemas\CouponForm;
use App\Filament\Support\Admin;
use App\Models\Coupon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->weight('bold')->searchable()->copyable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => CouponForm::TYPES[$state] ?? $state),
                TextColumn::make('value')->formatStateUsing(fn (Coupon $r) => match ($r->type) {
                    'percent_items' => $r->value.'%'.($r->max_discount_bdt ? ' (max '.Admin::bdt($r->max_discount_bdt).')' : ''),
                    'fixed' => Admin::bdt($r->value),
                    default => 'Free shipping',
                }),
                TextColumn::make('min_subtotal_bdt')->label('Min subtotal')->formatStateUsing(fn ($state) => Admin::bdt($state)),
                TextColumn::make('used')->formatStateUsing(fn (Coupon $r) => $r->used.($r->max_uses ? ' / '.$r->max_uses : '')),
                TextColumn::make('expires_at')->dateTime()->sortable()->color(fn (Coupon $r) => $r->expires_at?->isPast() ? 'danger' : null),
                ToggleColumn::make('active'),
            ])
            ->filters([
                SelectFilter::make('type')->options(CouponForm::TYPES),
                TernaryFilter::make('active'),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()->visible(fn () => Admin::canManage())]),
            ]);
    }
}

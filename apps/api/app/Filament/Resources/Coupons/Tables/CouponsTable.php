<?php

namespace App\Filament\Resources\Coupons\Tables;

use App\Filament\Resources\Coupons\Schemas\CouponForm;
use App\Filament\Support\Admin;
use App\Models\Coupon;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\ToggleColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class CouponsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            // Read-only stats from orders that used the coupon (cancelled orders excluded).
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withSum(['orders as discount_given' => fn ($o) => $o->where('status', '!=', 'cancelled')], 'coupon_discount_bdt')
                ->withMax('orders as last_used_at', 'created_at'))
            ->columns([
                TextColumn::make('code')->weight('bold')->searchable()->copyable(),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => CouponForm::TYPES[$state] ?? $state),
                TextColumn::make('value')->formatStateUsing(fn (Coupon $r) => match ($r->type) {
                    'percent_items' => $r->value.'%'.($r->max_discount_bdt ? ' (max '.Admin::bdt($r->max_discount_bdt).')' : ''),
                    'fixed' => Admin::bdt($r->value),
                    default => 'Free shipping',
                }),
                TextColumn::make('store')->badge()->formatStateUsing(fn ($state) => CouponForm::stores()[$state ?: 'all'] ?? $state)->toggleable(),
                TextColumn::make('min_subtotal_bdt')->label('Min subtotal')->formatStateUsing(fn ($state) => Admin::bdt($state))->toggleable(),
                TextColumn::make('used')->label('Uses')->sortable()
                    ->formatStateUsing(fn (Coupon $r) => $r->used.($r->max_uses ? ' / '.$r->max_uses : ''))
                    ->description(fn (Coupon $r) => $r->per_customer_limit ? $r->per_customer_limit.' per customer' : 'no per-customer limit'),
                TextColumn::make('discount_given')->label('Discount given')->formatStateUsing(fn ($state) => Admin::bdt((float) $state))->default(0)->sortable(),
                TextColumn::make('last_used_at')->label('Last used')->dateTime('d M, H:i')->placeholder('Never')->sortable(),
                TextColumn::make('starts_at')->label('Starts')->dateTime('d M Y, H:i')->placeholder('Now')->sortable()->toggleable(),
                TextColumn::make('expires_at')->label('Ends')->dateTime('d M Y, H:i')->placeholder('No end')->sortable()
                    ->color(fn (Coupon $r) => $r->expired() ? 'danger' : null),
                IconColumn::make('first_order_only')->label('1st order')->boolean()->toggleable(),
                IconColumn::make('featured')->label('Promo')->boolean(),
                ToggleColumn::make('active'),
            ])
            ->filters([
                SelectFilter::make('type')->options(CouponForm::TYPES),
                SelectFilter::make('store')->options(CouponForm::stores()),
                TernaryFilter::make('active'),
                TernaryFilter::make('featured')->label('Show as promo'),
                TernaryFilter::make('first_order_only')->label('First order only'),
                Filter::make('live')->label('Live now')->query(fn (Builder $query) => $query->live()),
                Filter::make('ended')->label('Ended')->query(fn (Builder $query) => $query->whereNotNull('expires_at')->where('expires_at', '<=', now())),
            ])
            ->recordActions([EditAction::make()])
            ->toolbarActions([
                BulkActionGroup::make([DeleteBulkAction::make()->visible(fn () => Admin::canManage())]),
            ]);
    }
}

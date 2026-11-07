<?php

namespace App\Filament\Resources\PointsLedgers\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\PointsLedger;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PointsLedgersTable
{
    public const TYPES = ['earn' => 'Earned', 'redeem' => 'Redeemed', 'expire' => 'Expired', 'adjust' => 'Adjusted', 'refund' => 'Refunded', 'signup' => 'Signup bonus'];

    public const COLORS = ['earn' => 'success', 'redeem' => 'info', 'expire' => 'gray', 'adjust' => 'warning', 'refund' => 'primary', 'signup' => 'success'];

    public static function configure(Table $table, bool $forCustomer = false): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns(array_values(array_filter([
                TextColumn::make('created_at')->label('When')->dateTime('d M Y, H:i')->sortable(),
                $forCustomer ? null : TextColumn::make('user.name')->label('Customer')->searchable(['name', 'phone'])
                    ->description(fn (PointsLedger $r) => $r->user?->phone)
                    ->url(fn (PointsLedger $r) => UserResource::getUrl('view', ['record' => $r->user_id])),
                TextColumn::make('type')->badge()->formatStateUsing(fn ($state) => self::TYPES[$state] ?? $state)
                    ->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextColumn::make('points')->alignEnd()->weight('bold')
                    ->formatStateUsing(fn ($state) => ($state > 0 ? '+' : '').number_format($state))
                    ->color(fn ($state) => $state >= 0 ? 'success' : 'danger')
                    ->summarize(Sum::make()->label('Net')),
                TextColumn::make('order_id')->label('Order')->placeholder('—')
                    ->url(fn (PointsLedger $r) => $r->order_id ? OrderResource::getUrl('view', ['record' => $r->order_id]) : null),
                TextColumn::make('note')->limit(50)->placeholder('—'),
                TextColumn::make('expires_at')->label('Expires')->date()->placeholder('—')->toggleable(),
            ])))
            ->filters([
                SelectFilter::make('type')->options(self::TYPES)->multiple(),
                Filter::make('created_at')->label('Date')
                    ->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $query, array $data) => $query
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
                Filter::make('expiring')->label('Expiring in 30 days')
                    ->query(fn (Builder $query) => $query->where('points', '>', 0)->whereNull('settled_at')->whereBetween('expires_at', [now(), now()->addDays(30)])),
            ]);
    }
}

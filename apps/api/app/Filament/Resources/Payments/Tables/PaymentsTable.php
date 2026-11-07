<?php

namespace App\Filament\Resources\Payments\Tables;

use App\Filament\Resources\Orders\Actions\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Models\Payment;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\Summarizers\Sum;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class PaymentsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('#')->sortable(),
                TextColumn::make('order_id')->label('Order')->searchable()->weight('bold')
                    ->url(fn (Payment $r) => OrderResource::getUrl('view', ['record' => $r->order_id])),
                TextColumn::make('gateway')->badge()->sortable(),
                TextColumn::make('kind')->placeholder('full')->toggleable(),
                TextColumn::make('amount_bdt')->label('Amount')->formatStateUsing(fn ($state) => Admin::bdt($state))->sortable()->alignEnd()
                    ->summarize(Sum::make()->label('Total')->formatStateUsing(fn ($state) => Admin::bdt($state))),
                TextColumn::make('refunded_bdt')->label('Refunded')->formatStateUsing(fn ($state) => $state > 0 ? Admin::bdt($state) : '—')->alignEnd()
                    ->summarize(Sum::make()->label('Refunded')->formatStateUsing(fn ($state) => Admin::bdt($state))),
                TextColumn::make('status')->badge()->color(fn ($state) => Admin::PAYMENT_STATUS_COLORS[$state] ?? 'gray')->sortable(),
                TextColumn::make('gateway_ref')->label('Gateway ref')->searchable()->copyable()->placeholder('—')->toggleable(),
                TextColumn::make('refund_ref')->label('Refund ref')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->dateTime('d M Y, H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('gateway')->options(['bkash' => 'bKash', 'nagad' => 'Nagad', 'card' => 'Card', 'sslcommerz' => 'SSLCommerz', 'cod' => 'Cash on delivery', 'mock' => 'Mock']),
                SelectFilter::make('status')->options(array_combine(array_keys(Admin::PAYMENT_STATUS_COLORS), array_map('ucfirst', array_keys(Admin::PAYMENT_STATUS_COLORS)))),
                Filter::make('created_at')->label('Date')
                    ->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d))),
            ])
            ->recordActions([
                ViewAction::make(),
                OrderActions::refundPayment(),
            ]);
    }
}

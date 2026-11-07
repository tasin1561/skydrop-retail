<?php

namespace App\Filament\Resources\Orders\Tables;

use App\Filament\Resources\Orders\Actions\OrderActions;
use App\Filament\Support\Admin;
use App\Models\Order;
use Filament\Actions\ActionGroup;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class OrdersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $q) => $q->with(['user', 'items']))
            ->defaultSort('created_at', 'desc')
            ->poll('60s')
            ->columns([
                TextColumn::make('id')->label('Order')->searchable()->weight('bold')->copyable()
                    ->description(fn (Order $r) => $r->created_at?->timezone('Asia/Dhaka')->format('d M, H:i')),
                TextColumn::make('user.name')->label('Customer')->searchable(['name', 'phone'])
                    ->description(fn (Order $r) => $r->user?->phone),
                TextColumn::make('status')->badge()->sortable()
                    ->formatStateUsing(fn ($state) => Admin::ORDER_STATUS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => Admin::ORDER_STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('markets')->label('Stores')->badge()->color('gray')
                    ->state(fn (Order $r) => $r->items->pluck('market')->unique()->map(fn ($m) => ['amazon' => 'Mega', 'flipkart' => 'Style', 'bigbasket' => 'Daily'][$m] ?? $m)->values()->all()),
                TextColumn::make('items_count')->label('Items')->state(fn (Order $r) => $r->items->sum('qty'))->alignEnd(),
                TextColumn::make('total_bdt')->label('Total')->formatStateUsing(fn ($state) => Admin::bdt($state))->sortable()->alignEnd(),
                TextColumn::make('payment_status')->label('Payment')->badge()
                    ->color(fn ($state) => Admin::PAYMENT_STATUS_COLORS[$state] ?? 'gray')
                    ->description(fn (Order $r) => $r->payment_method),
                TextColumn::make('tracking_code')->label('Tracking')->searchable()->toggleable()->placeholder('—'),
                TextColumn::make('address.district')->label('District')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('staff_notes')->label('Notes')->limit(30)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->label('Updated')->since()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->multiple()->options(Admin::ORDER_STATUS_LABELS),
                SelectFilter::make('market')->label('Store')->options(Admin::MARKETS)
                    ->query(fn (Builder $q, array $data) => $q->when($data['value'] ?? null, fn ($q, $m) => $q->whereHas('items', fn ($i) => $i->where('market', $m)))),
                SelectFilter::make('payment_status')->options(array_combine(array_keys(Admin::PAYMENT_STATUS_COLORS), array_map('ucfirst', array_keys(Admin::PAYMENT_STATUS_COLORS)))),
                SelectFilter::make('payment_method')->options(Order::PAYMENT_LABELS ?? []),
                Filter::make('created_at')->label('Date')
                    ->schema([DatePicker::make('from'), DatePicker::make('until')])
                    ->query(fn (Builder $q, array $data) => $q
                        ->when($data['from'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                        ->when($data['until'] ?? null, fn ($q, $d) => $q->whereDate('created_at', '<=', $d)))
                    ->indicateUsing(fn (array $data) => array_filter([
                        ($data['from'] ?? null) ? 'From '.$data['from'] : null,
                        ($data['until'] ?? null) ? 'Until '.$data['until'] : null,
                    ])),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    OrderActions::verify(),
                    OrderActions::markPurchased(),
                    OrderActions::handToSkydrop(),
                    OrderActions::advance(),
                    OrderActions::notes(),
                    OrderActions::invoice(),
                    OrderActions::cancel(),
                ]),
            ])
            ->toolbarActions([
                BulkActionGroup::make(OrderActions::bulk()),
            ]);
    }
}

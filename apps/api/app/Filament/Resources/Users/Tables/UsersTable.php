<?php

namespace App\Filament\Resources\Users\Tables;

use App\Filament\Resources\Users\Actions\CustomerActions;
use App\Filament\Support\Admin;
use App\Models\User;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UsersTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query
                ->withCount('orders')
                ->withSum(['orders as spent_bdt' => fn ($o) => $o->where('status', '!=', 'cancelled')], 'total_bdt')
                ->withMax('orders as last_order_at', 'created_at'))
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable()->weight('bold')
                    ->description(fn (User $r) => $r->email),
                TextColumn::make('phone')->searchable()->copyable(),
                TextColumn::make('orders_count')->label('Orders')->sortable()->alignEnd(),
                TextColumn::make('spent_bdt')->label('Total spent')->sortable()->alignEnd()
                    ->formatStateUsing(fn ($state) => Admin::bdt($state)),
                TextColumn::make('points_balance')->label('Points')->numeric()->sortable()->alignEnd(),
                TextColumn::make('last_order_at')->label('Last order')->since()->sortable()->placeholder('never'),
                TextColumn::make('blocked_at')->label('Status')->badge()
                    ->state(fn (User $r) => $r->blocked_at ? 'Blocked' : 'Active')
                    ->color(fn ($state) => $state === 'Blocked' ? 'danger' : 'success'),
                TextColumn::make('created_at')->label('Joined')->date()->sortable()->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('blocked')->label('Blocked')->nullable()->attribute('blocked_at'),
                Filter::make('has_orders')->label('Has orders')->query(fn (Builder $query) => $query->has('orders')),
            ])
            ->recordActions([
                ViewAction::make(),
                ActionGroup::make([
                    EditAction::make(),
                    CustomerActions::notify(),
                    CustomerActions::adjustPoints(),
                    CustomerActions::block(),
                ]),
            ]);
    }
}

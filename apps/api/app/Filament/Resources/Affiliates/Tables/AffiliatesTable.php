<?php

namespace App\Filament\Resources\Affiliates\Tables;

use App\Filament\Support\Admin;
use App\Models\Affiliate;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliatesTable
{
    public const COLORS = ['pending' => 'warning', 'approved' => 'success', 'suspended' => 'danger'];

    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('user')->withCount(['clicks', 'commissions'])
                ->withSum(['commissions as earned_bdt' => fn ($c) => $c->whereIn('status', ['pending', 'approved', 'paid'])], 'amount_bdt'))
            ->columns([
                TextColumn::make('code')->weight('bold')->searchable()->copyable(),
                TextColumn::make('user.name')->label('Shopper')->description(fn (Affiliate $r) => $r->user?->phone)->searchable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Affiliate::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextColumn::make('default_rate')->label('Rate')->formatStateUsing(fn ($state) => $state === null ? 'Category' : $state.'%')
                    ->placeholder('Category'),
                TextColumn::make('clicks_count')->label('Clicks')->sortable(),
                TextColumn::make('commissions_count')->label('Orders')->sortable(),
                TextColumn::make('earned_bdt')->label('Earned')->formatStateUsing(fn ($state) => Admin::bdt($state))->sortable(),
                TextColumn::make('payout_method')->label('Payout')->formatStateUsing(fn (Affiliate $r) => (Affiliate::PAYOUT_METHODS[$r->payout_method] ?? '').' '.$r->payout_number),
                TextColumn::make('created_at')->label('Applied')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(Affiliate::STATUSES)])
            // TODO(rbac): swap Admin::canManage() for Admin::can('affiliates.approve') / ('affiliates.rate') once the RBAC helper lands.
            ->recordActions([
                Action::make('approve')->label('Approve')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                    ->visible(fn (Affiliate $r) => $r->status !== 'approved' && Admin::canManage())
                    ->requiresConfirmation()
                    ->action(function (Affiliate $r) {
                        $r->forceFill(['status' => 'approved', 'approved_at' => $r->approved_at ?? now()])->save();
                        Notification::make()->title("{$r->code} approved")->success()->send();
                    }),
                Action::make('suspend')->label('Suspend')->icon(Heroicon::OutlinedNoSymbol)->color('danger')
                    ->visible(fn (Affiliate $r) => $r->status === 'approved' && Admin::canManage())
                    ->requiresConfirmation()->modalDescription('Their links stop earning. Existing commissions are kept.')
                    ->action(fn (Affiliate $r) => $r->forceFill(['status' => 'suspended'])->save()),
                Action::make('rate')->label('Rate')->icon(Heroicon::OutlinedReceiptPercent)->color('gray')
                    ->visible(fn () => Admin::canManage())
                    ->fillForm(fn (Affiliate $r) => ['default_rate' => $r->default_rate])
                    ->schema([TextInput::make('default_rate')->label('Own commission rate')->numeric()->minValue(0)->maxValue(50)->suffix('%')
                        ->helperText('Empty = category rates')])
                    ->action(fn (Affiliate $r, array $data) => $r->forceFill(['default_rate' => $data['default_rate'] === '' ? null : $data['default_rate']])->save()),
                EditAction::make(),
            ]);
    }
}

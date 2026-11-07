<?php

namespace App\Filament\Resources\AffiliatePayouts\Tables;

use App\Filament\Support\Admin;
use App\Models\Affiliate;
use App\Models\AffiliatePayout;
use App\Services\Growth\AffiliateService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

class AffiliatePayoutsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with('affiliate.user')->withCount('commissions'))
            ->columns([
                TextColumn::make('id')->label('#'),
                TextColumn::make('affiliate.code')->label('Affiliate')->weight('bold')->searchable()
                    ->description(fn (AffiliatePayout $r) => $r->affiliate?->user?->name),
                TextColumn::make('amount_bdt')->label('Amount')->formatStateUsing(fn ($state) => Admin::bdt($state))->sortable(),
                TextColumn::make('method')->formatStateUsing(fn (AffiliatePayout $r) => (Affiliate::PAYOUT_METHODS[$r->method] ?? $r->method).' '.$r->number)->copyable(),
                TextColumn::make('commissions_count')->label('Orders'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => AffiliatePayout::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => AffiliatePayout::COLORS[$state] ?? 'gray'),
                TextColumn::make('reference')->placeholder('—')->copyable(),
                TextColumn::make('created_at')->label('Requested')->since()->sortable(),
                TextColumn::make('paid_at')->since()->placeholder('—'),
            ])
            ->filters([SelectFilter::make('status')->options(AffiliatePayout::STATUSES)])
            ->recordActions([
                Action::make('markPaid')->label('Mark paid')->icon(Heroicon::OutlinedCheckBadge)->color('success')
                    ->visible(fn (AffiliatePayout $r) => $r->status === 'requested' && Admin::canManage())
                    ->modalDescription(fn (AffiliatePayout $r) => 'Send '.Admin::bdt($r->amount_bdt).' to '.$r->method.' '.$r->number.', then enter the transaction id.')
                    ->schema([TextInput::make('reference')->label('Transaction id')->required()->maxLength(100)])
                    ->action(function (AffiliatePayout $r, array $data) {
                        app(AffiliateService::class)->markPaid($r, $data['reference']);
                        Notification::make()->title('Payout marked paid')->success()->send();
                    }),
                Action::make('reject')->label('Reject')->icon(Heroicon::OutlinedXCircle)->color('danger')
                    ->visible(fn (AffiliatePayout $r) => $r->status === 'requested' && Admin::canManage())
                    ->modalDescription('The commissions go back to the affiliate\'s available balance.')
                    ->schema([Textarea::make('note')->rows(2)])
                    ->action(fn (AffiliatePayout $r, array $data) => app(AffiliateService::class)->rejectPayout($r, $data['note'] ?? null)),
            ]);
    }
}

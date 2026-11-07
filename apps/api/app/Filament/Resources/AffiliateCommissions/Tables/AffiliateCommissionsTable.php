<?php

namespace App\Filament\Resources\AffiliateCommissions\Tables;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Models\AffiliateCommission;
use App\Services\Growth\AffiliateService;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class AffiliateCommissionsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->modifyQueryUsing(fn ($query) => $query->with(['affiliate', 'order']))
            ->columns([
                TextColumn::make('affiliate.code')->label('Affiliate')->weight('bold')->searchable(),
                TextColumn::make('order_id')->label('Order')->searchable()
                    ->url(fn (AffiliateCommission $r) => OrderResource::getUrl('view', ['record' => $r->order_id]))
                    ->description(fn (AffiliateCommission $r) => Admin::ORDER_STATUS_LABELS[$r->order?->status] ?? null),
                TextColumn::make('base_bdt')->label('Base')->formatStateUsing(fn ($state) => Admin::bdt($state)),
                TextColumn::make('rate')->suffix('%'),
                TextColumn::make('amount_bdt')->label('Commission')->formatStateUsing(fn ($state) => Admin::bdt($state))->sortable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => AffiliateCommission::STATUSES[$state] ?? $state)
                    ->color(fn ($state) => AffiliateCommission::COLORS[$state] ?? 'gray'),
                TextColumn::make('approve_after')->label('Hold until')->since()->placeholder('Not delivered'),
                TextColumn::make('note')->limit(40)->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options(AffiliateCommission::STATUSES)->multiple(),
                SelectFilter::make('affiliate')->relationship('affiliate', 'code')->searchable(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('approve')->label('Approve')->icon(Heroicon::OutlinedCheckCircle)->color('success')
                        ->visible(fn () => Admin::canManage())->requiresConfirmation()
                        ->modalDescription('Approves pending commissions now, skipping the remaining hold.')
                        ->action(function (Collection $records) {
                            $n = $records->filter(fn (AffiliateCommission $c) => app(AffiliateService::class)->approve($c))->count();
                            Notification::make()->title("{$n} commission(s) approved")->success()->send();
                        })->deselectRecordsAfterCompletion(),
                    BulkAction::make('reverse')->label('Reverse')->icon(Heroicon::OutlinedArrowUturnLeft)->color('danger')
                        ->visible(fn () => Admin::canManage())
                        ->schema([TextInput::make('reason')->default('Reversed by staff')->required()->maxLength(200)])
                        ->action(function (Collection $records, array $data) {
                            $n = $records->filter(fn (AffiliateCommission $c) => app(AffiliateService::class)->reverse($c, $data['reason']))->count();
                            Notification::make()->title("{$n} commission(s) reversed")->warning()->send();
                        })->deselectRecordsAfterCompletion(),
                ]),
            ]);
    }
}

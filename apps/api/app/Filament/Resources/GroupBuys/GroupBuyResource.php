<?php

namespace App\Filament\Resources\GroupBuys;

use App\Filament\Resources\GroupBuys\Pages\ListGroupBuys;
use App\Filament\Resources\GroupBuys\Pages\ViewGroupBuy;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Models\GroupBuy;
use App\Services\Shopping\GroupBuyService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

/** Group buys: members, deadline and status; staff can force-close (places the orders now) or cancel. */
class GroupBuyResource extends Resource
{
    protected static ?string $model = GroupBuy::class;

    protected static ?string $slug = 'group-buys';

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 6;

    protected static ?string $navigationLabel = 'Group buys';

    public const STATUSES = ['open' => 'Open', 'closing' => 'Closing', 'closed' => 'Closed', 'cancelled' => 'Cancelled'];

    public const COLORS = ['open' => 'info', 'closing' => 'warning', 'closed' => 'success', 'cancelled' => 'gray'];

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('code')->searchable()->weight('bold')->copyable(),
                TextColumn::make('market')->formatStateUsing(fn ($state) => Admin::marketLabel($state)),
                TextColumn::make('owner.name')->label('Organiser')->description(fn (GroupBuy $g) => $g->owner?->phone),
                TextColumn::make('active_members_count')->counts('activeMembers')->label('Members'),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextColumn::make('closes_at')->label('Deadline')->dateTime()->description(fn (GroupBuy $g) => $g->status === 'open' ? $g->closes_at->diffForHumans() : $g->close_note),
                TextColumn::make('created_at')->since()->sortable(),
            ])
            ->filters([SelectFilter::make('status')->options(self::STATUSES)])
            ->recordActions([ViewAction::make(), self::forceClose(), self::cancel()]);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make()->columns(4)->columnSpanFull()->schema([
                TextEntry::make('code')->copyable(),
                TextEntry::make('market')->formatStateUsing(fn ($state) => Admin::marketLabel($state)),
                TextEntry::make('status')->badge()->formatStateUsing(fn ($state) => self::STATUSES[$state] ?? $state)->color(fn ($state) => self::COLORS[$state] ?? 'gray'),
                TextEntry::make('closes_at')->label('Deadline')->dateTime(),
                TextEntry::make('owner.name')->label('Organiser'),
                TextEntry::make('shipping')->label('Shipping (solo → group)')->state(function (GroupBuy $g) {
                    $t = app(GroupBuyService::class)->split($g);

                    return Admin::bdt($t['soloShippingBdt']).' → '.Admin::bdt($t['groupShippingBdt']).' (saves '.Admin::bdt($t['savingBdt']).')';
                }),
                TextEntry::make('close_note')->placeholder('—')->columnSpan(2),
            ]),
            Section::make('Members')->columnSpanFull()->schema([
                RepeatableEntry::make('members')->label('')->columns(5)->schema([
                    TextEntry::make('user.name')->label('Customer'),
                    TextEntry::make('user.phone')->label('Phone'),
                    TextEntry::make('items')->label('Items')->state(fn ($record) => collect($record->items)->map(fn ($i) => $i['qty'].'× '.$i['productId'])->implode(', ')),
                    TextEntry::make('status')->badge()->helperText(fn ($record) => $record->error),
                    TextEntry::make('order_id')->label('Order')->placeholder('—')
                        ->url(fn ($record) => $record->order_id ? OrderResource::getUrl('view', ['record' => $record->order_id]) : null),
                ]),
            ]),
        ]);
    }

    public static function forceClose(): Action
    {
        return Action::make('forceClose')->label('Force close')->icon(Heroicon::OutlinedLockClosed)->color('warning')
            ->visible(fn (GroupBuy $record) => $record->status === 'open' && Admin::can('groups.close'))->requiresConfirmation()
            ->modalDescription('Places every member\'s order now (with the group shipping discount when there are 2+ members).')
            ->action(function (GroupBuy $record) {
                $r = app(GroupBuyService::class)->close($record, 'Closed by staff');
                Notification::make()->title("{$r['placed']} orders placed, {$r['failed']} failed")->success()->send();
            });
    }

    public static function cancel(): Action
    {
        return Action::make('cancelGroup')->label('Cancel')->icon(Heroicon::OutlinedXCircle)->color('danger')
            ->visible(fn (GroupBuy $record) => $record->status === 'open' && Admin::can('groups.cancel'))->requiresConfirmation()
            ->action(fn (GroupBuy $record) => app(GroupBuyService::class)->cancel($record, 'Cancelled by staff'));
    }

    public static function getNavigationBadge(): ?string
    {
        $n = GroupBuy::query()->where('status', 'open')->count();

        return $n ? (string) $n : null;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function getPages(): array
    {
        return ['index' => ListGroupBuys::route('/'), 'view' => ViewGroupBuy::route('/{record}')];
    }
}

<?php

namespace App\Filament\Pages\Hub;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Resources\Orders\Actions\OrderActions;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Models\Fulfilment\HubBox;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Fulfilment\HubService;
use App\Services\Fulfilment\UnboxingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Facades\Filament;
use Filament\Forms\Components\Radio;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Throwable;
use UnitEnum;

/** Orders at the Kolkata hub: pack received items into one box, print the label, then hand to Skydrop. */
class HubConsolidation extends Page implements HasTable
{
    use HasPagePermission;

    /** @var list<string> */
    protected static array $requiredPermissions = ['hub.receive'];

    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArchiveBox;

    protected static string|UnitEnum|null $navigationGroup = 'Hub';

    protected static ?int $navigationSort = 2;

    protected static ?string $navigationLabel = 'Pack & label';

    protected static ?string $title = 'Pack & label';

    protected static ?string $slug = 'hub/consolidation';

    public static function query(): Builder
    {
        return Order::query()->where(fn ($q) => $q->where('status', 'india_hub')
            ->orWhere(fn ($q) => $q->where('status', 'purchased')->whereIn('id', OrderItem::query()->select('order_id')->whereNotNull('hub_received_at'))));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(self::query())
            ->description('Pack every received item of an order into one outbound box, print its label, then hand it to Skydrop. Orders waiting for the customer to approve an unboxing stay here.')
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('id')->label('Order')->searchable()->weight('bold')->fontFamily('mono')
                    ->url(fn (Order $r) => OrderResource::getUrl('view', ['record' => $r])),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Admin::ORDER_STATUS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => Admin::ORDER_STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('received')->label('Received')
                    ->state(fn (Order $r) => collect(app(HubService::class)->progress($r))->map(fn ($p, $m) => ucfirst($m).' '.$p['received'].'/'.$p['total'])->implode(' · ')),
                TextColumn::make('address.district')->label('City'),
                TextColumn::make('actual_weight_kg')->label('Weight')->suffix(' kg')->numeric(2)->placeholder('—')
                    ->description(fn (Order $r) => $r->weight_flag && empty($r->weight_flag['resolution']) ? '+'.$r->weight_flag['variancePct'].'% vs estimate' : null)
                    ->color(fn (Order $r) => $r->weight_flag && empty($r->weight_flag['resolution']) ? 'danger' : null),
                TextColumn::make('unboxing_status')->label('Unboxing')->badge()->placeholder('—')
                    ->formatStateUsing(fn ($state) => match ($state) {
                        UnboxingService::AWAITING => 'Waiting for customer', UnboxingService::APPROVED => 'Approved',
                        UnboxingService::AUTO => 'Auto-approved', UnboxingService::PROBLEM => 'Problem reported', default => $state,
                    })
                    ->color(fn ($state) => match ($state) {
                        UnboxingService::AWAITING => 'warning', UnboxingService::PROBLEM => 'danger', default => 'success',
                    }),
                TextColumn::make('box')->label('Box')->placeholder('not packed')
                    ->state(fn (Order $r) => HubBox::query()->where('order_id', $r->id)->latest('id')->value('code')),
                TextColumn::make('skydrop_consignment_id')->label('Consignment')->placeholder('—')->toggleable(),
            ])
            ->filters([
                TernaryFilter::make('weight')->label('Weight flagged')
                    ->queries(true: fn ($q) => $q->whereNotNull('weight_flag'), false: fn ($q) => $q->whereNull('weight_flag')),
            ])
            ->recordActions([
                Action::make('pack')->label('Pack box')->icon(Heroicon::OutlinedArchiveBox)->color('primary')
                    ->visible(fn (Order $r) => $r->status === 'india_hub')
                    ->requiresConfirmation()->modalDescription('Groups every received item of this order into one outbound box and opens the label.')
                    ->action(function (Order $record, $livewire) {
                        try {
                            app(HubService::class)->consolidate($record, Filament::auth()->user());
                            $livewire->js('window.open('.json_encode(route('hub.labels', ['orders' => $record->id])).', "_blank")');
                            Notification::make()->title('Box packed')->success()->send();
                        } catch (Throwable $e) {
                            Notification::make()->title('Could not pack')->body($e->getMessage())->danger()->send();
                        }
                    }),
                Action::make('label')->label('Label')->icon(Heroicon::OutlinedPrinter)->color('gray')
                    ->visible(fn (Order $r) => HubBox::query()->where('order_id', $r->id)->exists())
                    ->url(fn (Order $r) => route('hub.labels', ['orders' => $r->id]), true),
                Action::make('weight')->label('Weight')->icon(Heroicon::OutlinedScale)->color('danger')
                    ->visible(fn (Order $r) => $r->weight_flag && empty($r->weight_flag['resolution']))
                    ->modalHeading('Heavier than estimated')
                    ->modalDescription(fn (Order $r) => "Measured {$r->weight_flag['actualKg']} kg vs {$r->weight_flag['estimateKg']} kg estimated (+{$r->weight_flag['variancePct']}%). Extra shipping about ".Admin::bdt($r->weight_flag['extraBdt']).'.')
                    ->schema([Radio::make('resolution')->required()->default('absorb')
                        ->options(['absorb' => 'Absorb the extra shipping', 'charge' => 'Charge the customer (sends a pay link)'])])
                    ->action(function (Order $record, array $data) {
                        app(HubService::class)->resolveWeight($record, $data['resolution']);
                        Notification::make()->title('Weight flag resolved')->success()->send();
                    }),
                OrderActions::handToSkydrop(),
            ])
            ->toolbarActions([
                BulkAction::make('labels')->label('Print labels')->icon(Heroicon::OutlinedPrinter)
                    ->action(fn (Collection $records, $livewire) => $livewire->js('window.open('.json_encode(route('hub.labels', ['orders' => $records->pluck('id')->implode(',')])).', "_blank")')),
            ])
            ->emptyStateHeading('Nothing at the hub')
            ->emptyStateDescription('Received orders appear here until they leave with Skydrop.');
    }
}

<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Filament\Support\Ops;
use App\Models\Order;
use App\Models\OrderItem;
use App\Services\Fulfilment\PriceAlertService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Grouping\Group;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use UnitEnum;

/** Items still to buy in India, grouped by marketplace, with direct links to the source product pages. */
class PurchaseQueue extends Page implements HasTable
{
    use HasPagePermission; // page.purchase-queue
    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQueueList;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 2;

    protected static ?string $title = 'Purchase queue';

    public static function getNavigationBadge(): ?string
    {
        $n = self::query()->count();

        return $n ? (string) $n : null;
    }

    public static function query(): Builder
    {
        return OrderItem::query()
            ->whereNull('purchase_ref')
            ->whereNull('cancelled_at') // fulfilment: items cancelled after a price alert
            ->whereIn('order_id', Order::query()->goods()->select('id')->whereIn('status', ['placed', 'verified'])
                ->where(fn ($q) => $q->where('is_preorder', false)->orWhere('status', '!=', 'placed'))); // Shopping: unpaid pre-orders wait for stock
    }

    public function content(Schema $schema): Schema
    {
        // Fulfilment: red banner for supplier price alerts (old vs new price, margin impact).
        return $schema->components([View::make('filament.hub.price-alert-banner'), EmbeddedTable::make()]);
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(self::query())
            ->description('Buy each item on the source store, then enter the marketplace order reference. When every item of an order has a reference the order moves to "Bought in India".')
            ->defaultGroup(Group::make('market')->label('Store')->getTitleFromRecordUsing(fn (OrderItem $r) => Admin::marketLabel($r->market)))
            ->defaultSort('created_at')
            ->columns([
                ImageColumn::make('image')->label('')->imageSize(40)->extraImgAttributes(['loading' => 'lazy', 'style' => 'object-fit:contain;background:#fff']),
                TextColumn::make('title')->limit(70)->wrap()->searchable()
                    ->description(fn (OrderItem $r) => $r->product_id.($r->variant ? ' · '.$r->variant : ''))
                    ->url(fn (OrderItem $r) => Admin::sourceUrl($r->market, $r->product_id), true)
                    ->color('primary'),
                TextColumn::make('qty')->alignEnd(),
                TextColumn::make('unit_inr')->label('Unit ₹')->numeric()->alignEnd(),
                TextColumn::make('line_inr')->label('Line ₹')->state(fn (OrderItem $r) => $r->unit_inr * $r->qty)->numeric()->alignEnd(),
                TextColumn::make('order_id')->label('Order')->searchable()
                    ->url(fn (OrderItem $r) => OrderResource::getUrl('view', ['record' => $r->order_id]))
                    ->badge()->color(fn (OrderItem $r) => Admin::ORDER_STATUS_COLORS[Order::query()->whereKey($r->order_id)->value('status')] ?? 'gray'),
                TextColumn::make('created_at')->label('Ordered')->since(),
            ])
            ->filters([
                SelectFilter::make('market')->label('Store')->options(Admin::MARKETS),
            ])
            ->recordActions([
                Action::make('bought')->label('Bought')->icon(Heroicon::OutlinedCheck)->color('success')->button() // main action first: it was cut off at 1280 px
                    ->visible(fn () => Admin::can('orders.mark_purchased'))
                    ->schema([TextInput::make('ref')->label('Marketplace order reference')->required()->maxLength(100),
                        TextInput::make('actual_paid_inr')->label('Actually paid (₹, all units)')->prefix('₹')->numeric()->minValue(0) // profit report
                            ->default(fn (OrderItem $record) => $record->unit_inr * $record->qty)])
                    ->action(fn (OrderItem $record, array $data) => $this->recordBought(collect([$record]), $data['ref'], $data['actual_paid_inr'] ?? null)),
                Action::make('recheckPrice')->label('Check price')->icon(Heroicon::OutlinedArrowPath)->color('gray')->iconButton()->tooltip('Check the live price') // fulfilment: right before buying
                    ->visible(fn () => Admin::can('orders.mark_purchased') && app(PriceAlertService::class)->enabled())
                    ->action(function (OrderItem $record) {
                        Ops::refreshProduct($record->product_id);
                        $flagged = app(PriceAlertService::class)->check($record);
                        Notification::make()->title($flagged ? 'Price alert: review before buying' : 'Price OK')->{$flagged ? 'danger' : 'success'}()->send();
                    }),
                Action::make('open')->label('Open')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')->iconButton()->tooltip('Open on the source store')
                    ->url(fn (OrderItem $r) => Admin::sourceUrl($r->market, $r->product_id), true),
            ])
            ->toolbarActions([
                BulkAction::make('boughtBulk')->label('Mark bought (one marketplace order)')->icon(Heroicon::OutlinedCheck)
                    ->visible(fn () => Admin::can('orders.mark_purchased'))
                    ->schema([TextInput::make('ref')->label('Marketplace order reference')->required()->maxLength(100),
                        TextInput::make('actual_paid_inr')->label('Actually paid for these items (₹ total)')->prefix('₹')->numeric()->minValue(0)
                            ->helperText('Optional; split across the items by their catalog price (profit report).')])
                    ->action(fn (Collection $records, array $data) => $this->recordBought($records, $data['ref'], $data['actual_paid_inr'] ?? null)),
            ])
            ->emptyStateHeading('Nothing to buy')
            ->emptyStateDescription('All placed and verified orders have their items bought.');
    }

    /** @param  \Illuminate\Support\Collection<int, OrderItem>  $items */
    protected function recordBought($items, string $ref, mixed $paidInr = null): void
    {
        $catalogInr = max(0.01, (float) $items->sum(fn ($i) => $i->unit_inr * $i->qty));
        foreach ($items as $item) {
            $fill = ['purchase_ref' => trim($ref)];
            if (is_numeric($paidInr)) { // actual purchase cost, split by catalog value (finance module)
                $fill['actual_paid_inr'] = round((float) $paidInr * ($item->unit_inr * $item->qty) / $catalogInr, 2);
            }
            $item->forceFill($fill)->save();
        }
        $moved = [];
        foreach ($items->pluck('order_id')->unique() as $orderId) {
            $order = Order::query()->with('items')->find($orderId);
            if ($order && $order->items->every(fn ($i) => filled($i->purchase_ref) || $i->cancelled_at) && in_array($order->status, ['placed', 'verified'], true)) {
                try {
                    Ops::markPurchased($order, [], 'All items bought from the source stores');
                    $moved[] = $order->id;
                } catch (\Throwable $e) {
                    Notification::make()->title("{$order->id}: {$e->getMessage()}")->danger()->send();
                }
            }
        }
        Notification::make()->title($items->count().' item(s) marked bought')
            ->body($moved ? 'Orders now "Bought in India": '.implode(', ', $moved) : null)->success()->send();
    }
}

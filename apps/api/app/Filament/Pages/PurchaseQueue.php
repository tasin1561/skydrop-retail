<?php

namespace App\Filament\Pages;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Filament\Support\Ops;
use App\Models\Order;
use App\Models\OrderItem;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\BulkAction;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
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
    use \App\Filament\Concerns\HasPagePermission; // page.purchase-queue
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
            ->whereIn('order_id', Order::query()->select('id')->whereIn('status', ['placed', 'verified'])
                ->where(fn ($q) => $q->where('is_preorder', false)->orWhere('status', '!=', 'placed'))); // Shopping: unpaid pre-orders wait for stock
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
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
                Action::make('open')->label('Open')->icon(Heroicon::OutlinedArrowTopRightOnSquare)->color('gray')
                    ->url(fn (OrderItem $r) => Admin::sourceUrl($r->market, $r->product_id), true),
                Action::make('bought')->label('Bought')->icon(Heroicon::OutlinedCheck)->color('success')
                    ->schema([TextInput::make('ref')->label('Marketplace order reference')->required()->maxLength(100)])
                    ->action(fn (OrderItem $record, array $data) => $this->recordBought(collect([$record]), $data['ref'])),
            ])
            ->toolbarActions([
                BulkAction::make('boughtBulk')->label('Mark bought (one marketplace order)')->icon(Heroicon::OutlinedCheck)
                    ->schema([TextInput::make('ref')->label('Marketplace order reference')->required()->maxLength(100)])
                    ->action(fn (Collection $records, array $data) => $this->recordBought($records, $data['ref'])),
            ])
            ->emptyStateHeading('Nothing to buy')
            ->emptyStateDescription('All placed and verified orders have their items bought.');
    }

    /** @param  \Illuminate\Support\Collection<int, OrderItem>  $items */
    protected function recordBought($items, string $ref): void
    {
        foreach ($items as $item) {
            $item->forceFill(['purchase_ref' => trim($ref)])->save();
        }
        $moved = [];
        foreach ($items->pluck('order_id')->unique() as $orderId) {
            $order = Order::query()->with('items')->find($orderId);
            if ($order && $order->items->every(fn ($i) => filled($i->purchase_ref)) && in_array($order->status, ['placed', 'verified'], true)) {
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

<?php

namespace App\Filament\Pages;

use App\Filament\Concerns\HasPagePermission;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Models\OrderItem;
use App\Services\Fulfilment\PriceAlertService;
use App\Support\Features;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\EmbeddedTable;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Concerns\InteractsWithTable;
use Filament\Tables\Contracts\HasTable;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Throwable;
use UnitEnum;

/** Supplier price alerts: ordered items whose source price rose or went out of stock before we bought them. */
class PriceAlerts extends Page implements HasTable
{
    use HasPagePermission { canAccess as permitted; }

    /** @var list<string> */
    protected static array $requiredPermissions = ['orders.mark_purchased'];

    public static function canAccess(): bool
    {
        return Features::enabled('supplier_price_alerts') && static::permitted();
    }

    use InteractsWithTable;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedArrowTrendingUp;

    protected static string|UnitEnum|null $navigationGroup = 'Sales';

    protected static ?int $navigationSort = 3;

    protected static ?string $title = 'Price alerts';

    protected static ?string $slug = 'price-alerts';

    public static function getNavigationBadge(): ?string
    {
        $n = self::query()->count();

        return $n ? (string) $n : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'danger';
    }

    public static function query(): Builder
    {
        // JSON "resolution" is null on open alerts; filtered again in PHP for portability.
        return OrderItem::query()->whereNotNull('price_alert')->whereNull('cancelled_at')->whereNull('purchase_ref')
            ->whereIn('id', app(PriceAlertService::class)->openAlerts()->pluck('id'));
    }

    public function content(Schema $schema): Schema
    {
        return $schema->components([EmbeddedTable::make()]);
    }

    private static function a(OrderItem $r, string $k): mixed
    {
        return ((array) $r->price_alert)[$k] ?? null;
    }

    public function table(Table $table): Table
    {
        return $table
            ->query(self::query())
            ->description('The source price rose by more than the alert threshold, or the item went out of stock, after the customer ordered. Decide before buying.')
            ->defaultSort('updated_at', 'desc')
            ->columns([
                ImageColumn::make('image')->label('')->imageSize(44)->extraImgAttributes(['style' => 'object-fit:contain;background:#fff']),
                TextColumn::make('title')->limit(60)->wrap()->description(fn (OrderItem $r) => Admin::marketLabel($r->market).' · Qty '.$r->qty)
                    ->url(fn (OrderItem $r) => Admin::sourceUrl($r->market, $r->product_id), true),
                TextColumn::make('order_id')->label('Order')->fontFamily('mono')->url(fn (OrderItem $r) => OrderResource::getUrl('view', ['record' => $r->order_id])),
                TextColumn::make('old_new')->label('Old → new (₹)')->color('danger')->weight('bold')
                    ->state(fn (OrderItem $r) => self::a($r, 'inStock') === false ? 'Out of stock'
                        : '₹'.number_format((float) self::a($r, 'oldInr')).' → ₹'.number_format((float) self::a($r, 'newInr')).' (+'.self::a($r, 'risePct').'%)'),
                TextColumn::make('margin')->label('Margin impact')
                    ->state(fn (OrderItem $r) => Admin::bdt(self::a($r, 'marginBeforeBdt')).' → '.Admin::bdt(self::a($r, 'marginAfterBdt')))
                    ->description(fn (OrderItem $r) => 'Extra cost '.Admin::bdt(self::a($r, 'extraBdt'))),
                TextColumn::make('price_checked_at')->label('Checked')->since(),
            ])
            ->recordActions([
                Action::make('recheck')->label('Re-check')->icon(Heroicon::OutlinedArrowPath)->color('gray')
                    ->action(fn (OrderItem $record) => self::run(fn () => app(PriceAlertService::class)->check($record), 'Re-checked')),
                Action::make('absorb')->label('Absorb')->icon(Heroicon::OutlinedCheck)->color('success')->requiresConfirmation()
                    ->visible(fn (OrderItem $r) => self::a($r, 'inStock') !== false)
                    ->modalDescription(fn (OrderItem $r) => 'We pay the extra '.Admin::bdt(self::a($r, 'extraBdt')).'; the customer is not charged.')
                    ->action(fn (OrderItem $record) => self::run(fn () => app(PriceAlertService::class)->absorb($record), 'Absorbed')),
                Action::make('ask')->label('Ask customer')->icon(Heroicon::OutlinedChatBubbleLeftRight)->color('warning')->requiresConfirmation()
                    ->visible(fn (OrderItem $r) => self::a($r, 'inStock') !== false)
                    ->modalDescription(fn (OrderItem $r) => 'Adds '.Admin::bdt(self::a($r, 'extraBdt')).' to the order and sends the customer a pay link.')
                    ->action(fn (OrderItem $record) => self::run(fn () => app(PriceAlertService::class)->askCustomer($record), 'Customer asked to pay the difference')),
                Action::make('switch')->label('Switch store')->icon(Heroicon::OutlinedArrowsRightLeft)->color('info')
                    ->visible(fn (OrderItem $r) => app(PriceAlertService::class)->matches($r)->isNotEmpty())
                    ->schema(fn (OrderItem $record) => [Select::make('product')->label('Matched listing')->required()
                        ->options(app(PriceAlertService::class)->matches($record)->mapWithKeys(fn ($p) => [$p->id => Admin::marketLabel($p->market).' · ₹'.number_format($p->price_inr).' · '.str($p->title)->limit(50)]))])
                    ->action(fn (OrderItem $record, array $data) => self::run(fn () => app(PriceAlertService::class)->switchStore($record, $data['product']), 'Switched store')),
                Action::make('cancelItem')->label('Cancel item')->icon(Heroicon::OutlinedXCircle)->color('danger')->requiresConfirmation()
                    ->modalDescription('Removes the item from the order and refunds what the customer paid for it.')
                    ->action(fn (OrderItem $record) => self::run(fn () => app(PriceAlertService::class)->cancelItem($record), 'Item cancelled and refunded')),
            ])
            ->emptyStateHeading('No price alerts')
            ->emptyStateDescription('Ordered items are re-checked every 2 hours and before you buy them.');
    }

    private static function run(callable $fn, string $ok): void
    {
        try {
            $fn();
            Notification::make()->title($ok)->success()->send();
        } catch (Throwable $e) {
            report($e);
            Notification::make()->title('Action failed')->body($e->getMessage())->danger()->send();
        }
    }
}

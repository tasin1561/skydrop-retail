<?php

namespace App\Filament\Widgets;

use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Support\Admin;
use App\Models\Order;
use App\Services\PricingService;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Filament\Widgets\TableWidget;
use Illuminate\Database\Eloquent\Builder;

/**
 * Open orders whose margin dropped below the threshold: what the customer paid for the items vs what the items
 * cost now (current product price in India, converted at today's FX rate with import duty). Catches price rises
 * between checkout and purchase.
 */
class LowMarginAlerts extends TableWidget
{
    protected static ?int $sort = 7;

    protected int|string|array $columnSpan = 'full';

    protected static ?string $heading = 'Low-margin alerts (open orders under 5%)';

    public const THRESHOLD = 5.0;

    public function table(Table $table): Table
    {
        $ids = self::lowMarginIds();

        return $table
            ->query(Order::query()->with('items')->whereIn('id', $ids ?: ['__none__']))
            ->defaultSort('created_at')
            ->columns([
                TextColumn::make('id')->label('Order')->weight('bold')->url(fn (Order $r) => OrderResource::getUrl('view', ['record' => $r])),
                TextColumn::make('status')->badge()->formatStateUsing(fn ($state) => Admin::ORDER_STATUS_LABELS[$state] ?? $state)
                    ->color(fn ($state) => Admin::ORDER_STATUS_COLORS[$state] ?? 'gray'),
                TextColumn::make('items_bdt')->label('Customer paid (items)')->state(fn (Order $r) => Admin::bdt($r->items->sum(fn ($i) => $i->unit_bdt * $i->qty))),
                TextColumn::make('cost_now')->label('Cost now')->state(fn (Order $r) => Admin::bdt(self::margin($r)['cost'])),
                TextColumn::make('margin')->label('Margin')->badge()
                    ->state(fn (Order $r) => round(self::margin($r)['pct'], 1).'%')
                    ->color(fn (Order $r) => self::margin($r)['pct'] < 0 ? 'danger' : 'warning'),
                TextColumn::make('created_at')->since(),
            ])
            ->paginated([5, 10, 25])
            ->emptyStateHeading('No low-margin orders')
            ->emptyStateDescription('All open orders keep at least '.self::THRESHOLD.'% margin at current prices.');
    }

    /** @return array<int, string> */
    public static function lowMarginIds(): array
    {
        return Order::query()->with('items')->whereIn('status', ['placed', 'verified'])->limit(500)->get()
            ->filter(fn (Order $o) => $o->items->isNotEmpty() && self::margin($o)['pct'] < self::THRESHOLD)
            ->pluck('id')->all();
    }

    /** @return array{cost: float, pct: float} */
    public static function margin(Order $o): array
    {
        static $memo = [];
        if (isset($memo[$o->id])) {
            return $memo[$o->id];
        }
        $pricing = app(PricingService::class);
        $fx = $pricing->fx() * (1 + (float) Admin::setting('pricing.fx_buffer', 0.015));
        $products = \App\Models\Product::query()->whereIn('id', $o->items->pluck('product_id'))->get()->keyBy('id');
        $paid = 0.0;
        $cost = 0.0;
        foreach ($o->items as $i) {
            $p = $products[$i->product_id] ?? null;
            $inr = $p?->price_inr ?: $i->unit_inr;
            $import = $pricing->rule($p?->category ?? '*')['import'];
            $cost += $inr * $fx * (1 + $import) * $i->qty;
            $paid += $i->unit_bdt * $i->qty;
        }

        return $memo[$o->id] = ['cost' => $cost, 'pct' => $paid > 0 ? ($paid - $cost) / $paid * 100 : 0];
    }

    public static function canView(): bool
    {
        return true;
    }
}

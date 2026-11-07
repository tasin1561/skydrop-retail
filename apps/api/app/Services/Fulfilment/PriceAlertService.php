<?php

namespace App\Services\Fulfilment;

use App\Filament\Pages\PriceAlerts;
use App\Filament\Support\Ops;
use App\Jobs\Fulfilment\CheckSourcePriceJob;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Product;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Notifier;
use App\Services\Commerce\OrderWorkflow;
use App\Support\Features;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Supplier price alerts: re-checks the source INR price of items that are ordered but not yet bought.
 * A rise above the threshold (or out of stock) flags the item; staff absorb it, ask the customer to pay the difference,
 * cancel the item with a refund or switch to a matched listing on another store.
 */
class PriceAlertService
{
    public const FETCH_JOB = 'App\\Jobs\\Catalog\\FetchProductJob';

    public const RESOLUTIONS = ['absorb' => 'Absorbed', 'customer_pays' => 'Customer pays the difference', 'cancelled' => 'Item cancelled', 'switched' => 'Switched store'];

    public function __construct(private Notifier $notifier, private OrderWorkflow $workflow) {}

    public function enabled(): bool
    {
        return Features::enabled('supplier_price_alerts');
    }

    /** @return Collection<int, OrderItem> items ordered but not bought yet */
    public function pendingItems(): Collection
    {
        return OrderItem::query()->whereNull('purchase_ref')->whereNull('cancelled_at')
            ->whereIn('order_id', Order::query()->select('id')->whereIn('status', ['placed', 'verified']))->get();
    }

    /**
     * Scheduled every 2 h. With $refresh the catalog job refreshes each product first (chained), else the latest products row is used.
     *
     * @return int products checked or queued
     */
    public function checkPending(bool $refresh = true): int
    {
        if (! $this->enabled()) {
            return 0;
        }
        $ids = $this->pendingItems()->pluck('product_id')->unique()->values();
        foreach ($ids as $id) {
            if ($refresh && class_exists(self::FETCH_JOB)) {
                Bus::chain([new (self::FETCH_JOB)($id, null, false), new CheckSourcePriceJob($id)])->dispatch();
            } else {
                $this->checkProduct($id);
            }
        }

        return $ids->count();
    }

    /** Compares every pending item of a product against the latest products row. */
    public function checkProduct(string $productId): int
    {
        $flagged = 0;
        foreach ($this->pendingItems()->where('product_id', $productId) as $item) {
            $flagged += $this->check($item) ? 1 : 0;
        }

        return $flagged;
    }

    /** Right before staff buy: optionally queue a refresh, then compare now. Returns true when the item is flagged. */
    public function check(OrderItem $item): bool
    {
        $product = Product::query()->find($item->product_id);
        $item->forceFill(['price_checked_at' => now()]);
        if (! $product) {
            $item->save();

            return false;
        }
        $old = (float) $item->unit_inr;
        $new = (float) $product->price_inr;
        $inStock = (bool) ($product->in_stock ?? true);
        $rise = $old > 0 ? round(($new - $old) / $old * 100, 1) : 0.0;
        $limit = FulfilmentSettings::float('price_alerts.rise_pct');
        $current = (array) $item->price_alert;
        $unresolved = $current && empty($current['resolution']);

        if ($rise <= $limit && $inStock) {
            if ($unresolved) {
                $item->price_alert = null; // back to normal before anyone acted
            }
            $item->save();
            $this->syncOrderFlag($item->order_id);

            return false;
        }
        if ($unresolved && (float) ($current['newInr'] ?? 0) === $new && ($current['inStock'] ?? true) === $inStock) {
            $item->save();

            return true; // already flagged with the same numbers
        }
        $fx = (float) ($item->fx_rate ?: Order::query()->whereKey($item->order_id)->value('fx_rate') ?: 1);
        $revenue = $item->unit_bdt * $item->qty;
        $extraInr = round(max(0, $new - $old) * $item->qty, 2);
        $item->price_alert = [
            'oldInr' => $old, 'newInr' => $new, 'risePct' => $rise, 'limitPct' => $limit, 'inStock' => $inStock,
            'extraInr' => $extraInr, 'extraBdt' => (int) ceil($extraInr * $fx),
            'marginBeforeBdt' => (int) round($revenue - $old * $item->qty * $fx), 'marginAfterBdt' => (int) round($revenue - $new * $item->qty * $fx),
            'at' => now()->toIso8601String(), 'resolution' => null,
        ];
        $item->save();
        $this->syncOrderFlag($item->order_id);

        $what = $inStock ? "price rose {$rise}% (₹".number_format($old).' → ₹'.number_format($new).')' : 'is out of stock';
        StaffAlerts::send("Price alert on {$item->order_id}", mb_strimwidth($item->title, 0, 60, '…')." {$what}. Margin impact ৳".number_format($item->price_alert['marginAfterBdt'] - $item->price_alert['marginBeforeBdt']).'.',
            rescue(fn () => PriceAlerts::getUrl(), null, false));

        return true;
    }

    public function syncOrderFlag(string $orderId): void
    {
        $open = OrderItem::query()->where('order_id', $orderId)->whereNull('cancelled_at')->whereNotNull('price_alert')->get()
            ->contains(fn (OrderItem $i) => empty($i->price_alert['resolution']));
        Order::query()->whereKey($orderId)->update(['price_alert' => $open]);
    }

    /** @return Collection<int, OrderItem> */
    public function openAlerts(): Collection
    {
        return OrderItem::query()->whereNotNull('price_alert')->whereNull('cancelled_at')->get()
            ->filter(fn (OrderItem $i) => empty($i->price_alert['resolution']))->values();
    }

    public function absorb(OrderItem $item): OrderItem
    {
        $a = $this->requireAlert($item);
        $item->forceFill(['unit_inr' => $a['newInr'], 'price_alert' => ['resolution' => 'absorb', 'resolvedAt' => now()->toIso8601String()] + $a])->save();
        $this->workflow->event(Order::query()->findOrFail($item->order_id), Order::query()->whereKey($item->order_id)->value('status'), 'admin',
            'Source price rose; we absorbed the difference');
        $this->syncOrderFlag($item->order_id);

        return $item;
    }

    /** Adds the difference to the order and sends the customer a pay link (POST /orders/{id}/pay-difference). */
    public function askCustomer(OrderItem $item): OrderItem
    {
        $a = $this->requireAlert($item);
        if (! $a['inStock']) {
            throw new CommerceException('The item is out of stock: cancel it or switch store instead.', 409, [], 'out_of_stock');
        }
        $diff = max(0, (int) $a['extraBdt']);
        $item->forceFill(['unit_inr' => $a['newInr'], 'unit_bdt' => $item->unit_bdt + ($item->qty ? $diff / $item->qty : 0),
            'price_alert' => ['resolution' => 'customer_pays', 'resolvedAt' => now()->toIso8601String()] + $a])->save();
        $order = Order::query()->findOrFail($item->order_id);
        $this->chargeCustomer($order, $diff, 'Price went up at the store for "'.mb_strimwidth($item->title, 0, 50, '…').'"', true);
        $this->syncOrderFlag($item->order_id);

        return $item;
    }

    /** Raises the order total by $amount and notifies the customer with a pay link. */
    public function chargeCustomer(Order $order, int $amount, string $reason, bool $itemsChanged = false): Order
    {
        if ($amount <= 0) {
            return $order;
        }
        DB::transaction(function () use ($order, $amount, $itemsChanged) {
            $order->total_bdt = $order->total_bdt + $amount;
            if ($itemsChanged) {
                $order->subtotal_bdt = $order->subtotal_bdt + $amount;
            } else {
                $order->shipping_bdt = $order->shipping_bdt + $amount;
            }
            if (in_array($order->payment_status, ['paid'], true)) {
                $order->payment_status = 'partial';
            }
            $order->save();
        });
        $this->workflow->event($order, $order->status, 'admin', $reason.'. Extra ৳'.number_format($amount).' to pay.');
        $order->loadMissing('user');
        if ($order->user) {
            $link = rtrim((string) config('retail.web_url'), '/').'/orders/'.$order->id.'?pay=1';
            $how = $order->payment_method === 'advance50' ? 'It will be added to the cash the rider collects.' : "Pay here: {$link}";
            rescue(fn () => $this->notifier->custom($order->user, "Skydrop Retail: {$reason} (order {$order->id}). Extra ৳".number_format($amount).". {$how}", 'sms'), null, true);
        }

        return $order->refresh();
    }

    /** Cancels one item and refunds its value (all items cancelled → the order is cancelled). */
    public function cancelItem(OrderItem $item, string $reason = 'Item no longer available at this price'): OrderItem
    {
        $a = (array) $item->price_alert;
        $order = Order::query()->findOrFail($item->order_id);
        $line = round($item->unit_bdt * $item->qty, 2);
        DB::transaction(function () use ($item, $order, $line, $a) {
            $item->forceFill(['cancelled_at' => now(), 'price_alert' => $a ? ['resolution' => 'cancelled', 'resolvedAt' => now()->toIso8601String()] + $a : null])->save();
            $order->forceFill(['subtotal_bdt' => max(0, $order->subtotal_bdt - $line), 'total_bdt' => max(0, $order->total_bdt - $line)])->save();
        });
        $this->workflow->event($order, $order->status, 'admin', 'Removed "'.mb_strimwidth($item->title, 0, 50, '…').'": '.$reason);

        if ($order->items()->whereNull('cancelled_at')->doesntExist()) {
            if ($order->canCancel()) {
                $this->workflow->cancel($order, 'admin', $reason);
            }
        } else {
            $this->refundAmount($order, min($line, max(0, (float) $order->paid_bdt - (float) $order->total_bdt)), "Item {$item->id} cancelled");
        }
        $this->syncOrderFlag($order->id);
        $order->loadMissing('user');
        if ($order->user) {
            rescue(fn () => $this->notifier->custom($order->user, 'Skydrop Retail: we removed "'.mb_strimwidth($item->title, 0, 50, '…')."\" from order {$order->id} ({$reason}). Any payment for it is refunded.", 'sms'), null, true);
        }

        return $item->refresh();
    }

    /** Partial refund across the order's online payments. */
    public function refundAmount(Order $order, float $amount, string $reason): float
    {
        $left = round($amount, 2);
        foreach ($order->payments()->where('status', 'paid')->where('gateway', '!=', 'skydrop_cod')->get() as $p) {
            if ($left <= 0) {
                break;
            }
            $part = min($left, (float) $p->amount_bdt - (float) $p->refunded_bdt);
            if ($part <= 0) {
                continue;
            }
            try {
                Ops::refund($p, $part, $reason);
                $left = round($left - $part, 2);
            } catch (Throwable $e) {
                report($e);
            }
        }

        return round($amount - $left, 2);
    }

    /** Matched listings on other stores (shopping module `product_matches`), cheapest first. */
    public function matches(OrderItem $item): Collection
    {
        if (! rescue(fn () => Schema::hasTable('product_matches'), false, false)) {
            return collect();
        }
        $rows = DB::table('product_matches')->where('status', '!=', 'rejected')
            ->where(fn ($q) => $q->where('product_id', $item->product_id)->orWhere('match_id', $item->product_id))->get();
        $ids = $rows->map(fn ($r) => $r->product_id === $item->product_id ? $r->match_id : $r->product_id)->unique()->values();

        return Product::query()->whereIn('id', $ids)->where('in_stock', true)->orderBy('price_inr')->get();
    }

    public function switchStore(OrderItem $item, string $productId): OrderItem
    {
        $p = $this->matches($item)->firstWhere('id', $productId);
        if (! $p) {
            throw CommerceException::field('product', 'Choose one of the matched listings.');
        }
        $a = (array) $item->price_alert;
        $from = $item->market;
        $item->forceFill(['product_id' => $p->id, 'market' => $p->market, 'title' => mb_substr($p->title, 0, 500), 'image' => $p->image ?? $item->image,
            'unit_inr' => (float) $p->price_inr,
            'price_alert' => ['resolution' => 'switched', 'switchedTo' => $p->id, 'resolvedAt' => now()->toIso8601String()] + $a])->save();
        $order = Order::query()->findOrFail($item->order_id);
        $this->workflow->event($order, $order->status, 'admin', 'Switched "'.mb_strimwidth($item->title, 0, 50, '…')."\" from {$from} to {$p->market} (same product, better price/stock)");
        $this->syncOrderFlag($item->order_id);

        return $item;
    }

    private function requireAlert(OrderItem $item): array
    {
        $a = (array) $item->price_alert;
        if (! $a || ! empty($a['resolution'])) {
            throw new CommerceException('This item has no open price alert.', 409, [], 'no_alert');
        }

        return $a;
    }
}

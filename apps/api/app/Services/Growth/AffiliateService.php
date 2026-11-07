<?php

namespace App\Services\Growth;

use App\Models\Affiliate;
use App\Models\AffiliateClick;
use App\Models\AffiliateCommission;
use App\Models\AffiliatePayout;
use App\Models\Coupon;
use App\Models\Order;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Phone;
use App\Support\Features;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Affiliate program. Attribution is last click: the storefront keeps the latest ?ref= code (cookie_days) and sends it
 * with POST /orders. Commission = item subtotal after item discounts (coupon on items, points; never shipping)
 * x category rate (or the affiliate's own rate), capped per order.
 * Lifecycle: pending (order placed) -> approved (delivered + hold_days) -> paid (payout marked paid);
 * reversed when the order is cancelled or refunded before payout.
 * Feature switch "affiliate": off = no new attribution, no hold start/auto-approval (reversals still run).
 */
class AffiliateService
{
    public const CODE_RULE = '/^[A-Z0-9]{4,16}$/';

    public static function normalizeCode(?string $code): ?string
    {
        $c = strtoupper(trim((string) $code));

        return preg_match(self::CODE_RULE, $c) ? $c : null;
    }

    public function findApproved(?string $code): ?Affiliate
    {
        $c = self::normalizeCode($code);

        return $c ? Affiliate::query()->where('code', $c)->where('status', 'approved')->first() : null;
    }

    // ---- Applying and profile ----

    public function apply(User $user, array $data): Affiliate
    {
        if ($existing = Affiliate::query()->where('user_id', $user->id)->first()) {
            return $existing;
        }
        $code = isset($data['code']) ? self::normalizeCode($data['code']) : null;
        if (isset($data['code']) && ! $code) {
            throw CommerceException::field('code', 'Use 4-16 letters or digits.');
        }
        if ($code && $this->codeTaken($code)) {
            throw CommerceException::field('code', 'That code is taken. Try another.');
        }
        $number = isset($data['payoutNumber']) ? Phone::normalize($data['payoutNumber']) : null;
        if (! empty($data['payoutNumber']) && ! $number) {
            throw CommerceException::field('payoutNumber', 'Enter a valid bKash/Nagad number (01XXXXXXXXX).');
        }

        return Affiliate::query()->create([
            'user_id' => $user->id, 'code' => $code ?? $this->suggestCode($user->name), 'status' => 'pending',
            'payout_method' => $data['payoutMethod'] ?? 'bkash', 'payout_number' => $number ?? $user->phone,
            'notes' => isset($data['about']) ? mb_substr((string) $data['about'], 0, 1000) : null,
        ]);
    }

    /** Code can be changed once; payout details any time. */
    public function update(Affiliate $a, array $data): Affiliate
    {
        if (array_key_exists('code', $data) && $data['code'] !== null) {
            $code = self::normalizeCode($data['code']) ?? throw CommerceException::field('code', 'Use 4-16 letters or digits.');
            if ($code !== $a->code) {
                if ($a->code_changed_at) {
                    throw CommerceException::field('code', 'Your code was already changed once.');
                }
                if ($this->codeTaken($code)) {
                    throw CommerceException::field('code', 'That code is taken. Try another.');
                }
                $a->code = $code;
                $a->code_changed_at = now();
            }
        }
        if (! empty($data['payoutMethod'])) {
            $a->payout_method = $data['payoutMethod'];
        }
        if (array_key_exists('payoutNumber', $data) && $data['payoutNumber'] !== null) {
            $a->payout_number = Phone::normalize($data['payoutNumber'])
                ?? throw CommerceException::field('payoutNumber', 'Enter a valid bKash/Nagad number (01XXXXXXXXX).');
        }
        $a->save();

        return $a;
    }

    public function suggestCode(string $name): string
    {
        $base = substr(preg_replace('/[^A-Z]/', '', strtoupper(Str::ascii(explode(' ', trim($name))[0] ?? ''))), 0, 8);
        $base = strlen($base) >= 3 ? $base : 'SKY';
        do {
            $code = $base.random_int(10, 999);
        } while ($this->codeTaken($code));

        return $code;
    }

    private function codeTaken(string $code): bool
    {
        return Affiliate::query()->where('code', $code)->exists();
    }

    // ---- Clicks ----

    /** Records a click for an approved affiliate. Same visitor + product within 30 minutes counts once. */
    public function recordClick(string $code, ?string $productId, ?string $url, ?string $ip, ?string $ua): bool
    {
        $a = $this->findApproved($code);
        if (! $a) {
            return false;
        }
        $ipHash = $ip ? hash_hmac('sha256', $ip, (string) config('app.key')) : null;
        $dupe = $ipHash && AffiliateClick::query()->where('affiliate_id', $a->id)->where('ip_hash', $ipHash)
            ->where('product_id', $productId)->where('created_at', '>=', now()->subMinutes(30))->exists();
        if (! $dupe) {
            AffiliateClick::query()->create([
                'affiliate_id' => $a->id, 'product_id' => $productId ? mb_substr($productId, 0, 40) : null,
                'landing_url' => $url ? mb_substr($url, 0, 500) : null, 'ip_hash' => $ipHash,
                'ua' => $ua ? mb_substr($ua, 0, 255) : null, 'created_at' => now(),
            ]);
        }

        return true;
    }

    // ---- Orders ----

    /** Called once after an order is created with an optional ?ref code. */
    public function attribute(Order $order, ?string $ref): ?AffiliateCommission
    {
        $code = self::normalizeCode($ref);
        if (! $code || ! Features::enabled('affiliate') || ! $order->isGoods()) { // no commission on gift cards / Plus
            return null;
        }
        $order->forceFill(['ref_code' => $code])->saveQuietly();
        $a = $this->findApproved($code);
        if (! $a || $this->isSelfReferral($a, $order)) {
            return null;
        }
        [$base, $amount] = $this->commissionFor($order, $a);
        $order->forceFill(['affiliate_id' => $a->id])->saveQuietly();
        if ($amount <= 0) {
            return null;
        }

        return AffiliateCommission::query()->firstOrCreate(['order_id' => $order->id], [
            'affiliate_id' => $a->id, 'base_bdt' => $base, 'rate' => $base > 0 ? round($amount / $base * 100, 2) : 0,
            'amount_bdt' => $amount, 'status' => 'pending',
        ]);
    }

    /** Same account, or the buyer's / delivery phone matches the affiliate's account or payout number. */
    public function isSelfReferral(Affiliate $a, Order $order): bool
    {
        if ($a->user_id === $order->user_id) {
            return true;
        }
        $affPhones = array_filter([Phone::normalize($a->user?->phone), Phone::normalize($a->payout_number)]);
        $buyer = array_filter([Phone::normalize($order->user?->phone), Phone::normalize($order->address['phone'] ?? null)]);

        return (bool) array_intersect($affPhones, $buyer);
    }

    /** @return array{0: float, 1: float} [base_bdt, amount_bdt] */
    public function commissionFor(Order $order, Affiliate $a): array
    {
        $order->loadMissing('items');
        $subtotal = (float) $order->subtotal_bdt;
        if ($subtotal <= 0 || ! $order->isGoods()) {
            return [0.0, 0.0];
        }
        $couponItems = (float) $order->coupon_discount_bdt;
        if ($order->coupon_code && Coupon::query()->where('code', $order->coupon_code)->value('type') === 'free_shipping') {
            $couponItems = 0.0;
        }
        $base = max(0.0, $subtotal - $couponItems - (float) $order->points_discount_bdt);
        $ratio = $base / $subtotal;
        $categories = Product::query()->whereIn('id', $order->items->pluck('product_id'))->pluck('category', 'id');
        $amount = 0.0;
        foreach ($order->items as $i) {
            $rate = $a->default_rate !== null ? (float) $a->default_rate : GrowthSettings::rate($categories[$i->product_id] ?? null);
            $amount += $i->unit_bdt * $i->qty * $ratio * $rate / 100;
        }
        $cap = GrowthSettings::int('affiliate.max_per_order_bdt');
        if ($cap > 0) {
            $amount = min($amount, $cap);
        }

        return [round($base, 2), round($amount, 2)];
    }

    public function onDelivered(Order $order): void
    {
        if (! Features::enabled('affiliate')) {
            return; // paused: stays pending; approve from the admin if needed
        }
        $c = AffiliateCommission::query()->where('order_id', $order->id)->where('status', 'pending')->first();
        if (! $c) {
            return;
        }
        $hold = max(0, GrowthSettings::int('affiliate.hold_days'));
        $c->approve_after = ($order->delivered_at ?? now())->copy()->addDays($hold);
        $c->save();
        if ($c->approve_after->lte(now())) {
            $this->approve($c);
        }
    }

    /** Cancelled or refunded: the commission is reversed unless already paid out. */
    public function reverseForOrder(Order $order, string $reason): void
    {
        $c = AffiliateCommission::query()->where('order_id', $order->id)->first();
        if ($c) {
            $this->reverse($c, $reason);
        }
    }

    public function approve(AffiliateCommission $c): bool
    {
        if ($c->status !== 'pending') {
            return false;
        }
        $c->forceFill(['status' => 'approved', 'approved_at' => now()])->save();

        return true;
    }

    public function reverse(AffiliateCommission $c, string $reason = 'Reversed'): bool
    {
        if (! in_array($c->status, ['pending', 'approved'], true)) {
            return false;
        }
        DB::transaction(function () use ($c, $reason) {
            if ($c->payout_id && ($p = AffiliatePayout::query()->lockForUpdate()->find($c->payout_id)) && $p->status === 'requested') {
                $p->forceFill(['amount_bdt' => max(0, $p->amount_bdt - $c->amount_bdt)])->save();
            }
            $c->forceFill(['status' => 'reversed', 'reversed_at' => now(), 'payout_id' => null, 'note' => mb_substr($reason, 0, 250)])->save();
        });

        return true;
    }

    /** Approves pending commissions whose hold has passed (scheduled hourly). */
    public function approveDue(): int
    {
        if (! Features::enabled('affiliate')) {
            return 0;
        }
        $n = 0;
        AffiliateCommission::query()->where('status', 'pending')->whereNotNull('approve_after')->where('approve_after', '<=', now())
            ->with('order')->get()
            ->each(function (AffiliateCommission $c) use (&$n) {
                if ($c->order?->status === 'delivered' && ! in_array($c->order->payment_status, ['refunded', 'refund_pending'], true)) {
                    $n += (int) $this->approve($c);
                }
            });

        return $n;
    }

    // ---- Payouts ----

    public function available(Affiliate $a): float
    {
        return (float) AffiliateCommission::query()->where('affiliate_id', $a->id)->where('status', 'approved')->whereNull('payout_id')->sum('amount_bdt');
    }

    /** Bundles every approved, unpaid commission into one payout request. */
    public function requestPayout(Affiliate $a): AffiliatePayout
    {
        if (! $a->isApproved()) {
            throw new CommerceException('Your affiliate account is not active.', 403, [], 'not_approved');
        }
        if (! $a->payout_method || ! $a->payout_number) {
            throw CommerceException::field('payoutNumber', 'Add your bKash or Nagad number first.');
        }
        if (AffiliatePayout::query()->where('affiliate_id', $a->id)->where('status', 'requested')->exists()) {
            throw new CommerceException('You already have a payout request in progress.', 409, [], 'payout_pending');
        }

        return DB::transaction(function () use ($a) {
            $min = GrowthSettings::int('affiliate.min_payout_bdt');
            $rows = AffiliateCommission::query()->where('affiliate_id', $a->id)->where('status', 'approved')->whereNull('payout_id')->lockForUpdate()->get();
            $amount = round((float) $rows->sum('amount_bdt'), 2);
            if ($amount < $min || $amount <= 0) {
                throw new CommerceException('You can request a payout from ৳'.number_format($min).' of approved earnings.', 422,
                    ['availableBdt' => (int) floor($amount), 'minPayoutBdt' => $min], 'below_minimum');
            }
            $p = AffiliatePayout::query()->create([
                'affiliate_id' => $a->id, 'amount_bdt' => $amount, 'method' => $a->payout_method, 'number' => $a->payout_number, 'status' => 'requested',
            ]);
            AffiliateCommission::query()->whereIn('id', $rows->pluck('id'))->update(['payout_id' => $p->id]);

            return $p;
        });
    }

    public function markPaid(AffiliatePayout $p, string $reference): void
    {
        if ($p->status !== 'requested') {
            throw new CommerceException('Only requested payouts can be marked paid.', 409);
        }
        DB::transaction(function () use ($p, $reference) {
            $p->forceFill(['status' => 'paid', 'reference' => $reference, 'paid_at' => now()])->save();
            AffiliateCommission::query()->where('payout_id', $p->id)->where('status', 'approved')->update(['status' => 'paid', 'paid_at' => now()]);
        });
    }

    public function rejectPayout(AffiliatePayout $p, ?string $note = null): void
    {
        if ($p->status !== 'requested') {
            return;
        }
        DB::transaction(function () use ($p, $note) {
            $p->forceFill(['status' => 'rejected', 'notes' => $note])->save();
            AffiliateCommission::query()->where('payout_id', $p->id)->update(['payout_id' => null]);
        });
    }

    // ---- Dashboard ----

    public function link(Affiliate $a, ?string $path = '/'): string
    {
        return rtrim((string) config('retail.web_url'), '/').($path ?: '/').(str_contains((string) $path, '?') ? '&' : '?').'ref='.$a->code;
    }

    public function toApi(Affiliate $a): array
    {
        return [
            'code' => $a->code, 'status' => $a->status, 'rate' => $a->default_rate, 'payoutMethod' => $a->payout_method,
            'payoutNumber' => $a->payout_number, 'codeEditable' => $a->code_changed_at === null, 'link' => $this->link($a),
            'approvedAt' => $a->approved_at?->toIso8601String(), 'createdAt' => $a->created_at?->toIso8601String(),
        ];
    }

    public function stats(Affiliate $a): array
    {
        $sum = fn (array $statuses) => (int) floor((float) AffiliateCommission::query()->where('affiliate_id', $a->id)->whereIn('status', $statuses)->sum('amount_bdt'));
        $clicks = AffiliateClick::query()->where('affiliate_id', $a->id)->count();
        $orders = AffiliateCommission::query()->where('affiliate_id', $a->id)->where('status', '!=', 'reversed')->count();

        return [
            'clicks' => $clicks,
            'clicks30d' => AffiliateClick::query()->where('affiliate_id', $a->id)->where('created_at', '>=', now()->subDays(30))->count(),
            'orders' => $orders,
            'conversion' => $clicks > 0 ? round($orders / $clicks * 100, 1) : 0.0,
            'pendingBdt' => $sum(['pending']),
            'approvedBdt' => $sum(['approved']),
            'paidBdt' => $sum(['paid']),
            'availableBdt' => (int) floor($this->available($a)),
            'requestedBdt' => (int) floor((float) AffiliatePayout::query()->where('affiliate_id', $a->id)->where('status', 'requested')->sum('amount_bdt')),
        ];
    }

    /** Full GET /affiliate/me payload. */
    public function dashboard(User $user): array
    {
        $a = Affiliate::query()->where('user_id', $user->id)->first();
        if (! $a) {
            return ['affiliate' => null, 'program' => GrowthSettings::program()];
        }

        return [
            'affiliate' => $this->toApi($a),
            'stats' => $this->stats($a),
            'commissions' => AffiliateCommission::query()->where('affiliate_id', $a->id)->latest('id')->limit(20)->get()
                ->map(fn (AffiliateCommission $c) => [
                    'id' => $c->id, 'order' => AffiliateCommission::maskOrderId($c->order_id), 'baseBdt' => (int) round($c->base_bdt),
                    'rate' => $c->rate, 'amountBdt' => (int) round($c->amount_bdt), 'status' => $c->status,
                    'approveAfter' => $c->approve_after?->toIso8601String(), 'at' => $c->created_at?->toIso8601String(),
                ])->all(),
            'payouts' => AffiliatePayout::query()->where('affiliate_id', $a->id)->latest('id')->limit(10)->get()->map->toApi()->all(),
            'program' => GrowthSettings::program(),
        ];
    }
}

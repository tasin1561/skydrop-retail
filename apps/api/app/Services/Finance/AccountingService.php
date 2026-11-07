<?php

namespace App\Services\Finance;

use App\Models\Order;
use App\Models\Payment;
use App\Support\Knobs;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/** Accounting exports and the month-close summary. Every method returns [headers, rows] or plain arrays. */
class AccountingService
{
    public function __construct(private ProfitService $profit) {}

    /** @return array{0: array<int, string>, 1: array<int, array<int, mixed>>} */
    public function salesJournal(Carbon $from, Carbon $to): array
    {
        $rows = [];
        $orders = Order::query()->whereBetween('created_at', [$from->copy()->startOfDay(), $to->copy()->endOfDay()])->orderBy('created_at')->get();
        foreach ($orders->groupBy(fn ($o) => $o->created_at->toDateString()) as $day => $list) {
            $live = $list->where('status', '!=', 'cancelled');
            $fees = $live->sum(fn ($o) => (float) ($o->getAttributes()['unboxing_fee_bdt'] ?? 0) + (float) ($o->getAttributes()['emi_charge_bdt'] ?? 0));
            $rows[] = [$day, $live->count(), $list->count() - $live->count(), round($live->sum('subtotal_bdt'), 2), round($live->sum('shipping_bdt'), 2),
                round($fees, 2), round($live->sum(fn ($o) => (float) $o->discount_bdt - (float) $o->points_discount_bdt), 2),
                round($live->sum('points_discount_bdt'), 2), round($live->sum('total_bdt'), 2), round($live->sum('paid_bdt'), 2)];
        }

        return [['date', 'orders', 'cancelled', 'items_bdt', 'shipping_bdt', 'fees_bdt', 'discounts_bdt', 'points_bdt', 'net_sales_bdt', 'collected_bdt'], $rows];
    }

    public function payments(Carbon $from, Carbon $to): array
    {
        $rows = Payment::query()->whereIn('status', ['paid', 'refunded'])
            ->whereBetween(DB::raw('COALESCE(paid_at, created_at)'), [$from->copy()->startOfDay(), $to->copy()->endOfDay()])
            ->orderBy('gateway')->orderBy('paid_at')->get()
            ->map(function (Payment $p) {
                $fee = round((float) $p->amount_bdt * $this->profit->feePct($p->gateway, (string) $p->kind) / 100, 2);

                return [($p->paid_at ?? $p->created_at)?->toDateTimeString(), $p->gateway, $p->kind, $p->order_id, self::trxId($p),
                    (float) $p->amount_bdt, $fee, round((float) $p->amount_bdt - $fee, 2), (float) $p->refunded_bdt, $p->status];
            })->all();

        return [['paid_at', 'gateway', 'kind', 'order_id', 'trx_id', 'amount_bdt', 'est_fee_bdt', 'net_bdt', 'refunded_bdt', 'status'], $rows];
    }

    /** @return array<string, array{count: int, amount: float, fees: float}> */
    public function paymentsByGateway(Carbon $from, Carbon $to): array
    {
        $out = [];
        foreach ($this->payments($from, $to)[1] as $r) {
            $g = $r[1];
            $out[$g]['count'] = ($out[$g]['count'] ?? 0) + 1;
            $out[$g]['amount'] = round(($out[$g]['amount'] ?? 0) + $r[5], 2);
            $out[$g]['fees'] = round(($out[$g]['fees'] ?? 0) + $r[6], 2);
        }

        return $out;
    }

    public function refunds(Carbon $from, Carbon $to): array
    {
        $rows = [];
        Payment::query()->where('refunded_bdt', '>', 0)->get()->each(function (Payment $p) use (&$rows, $from, $to) {
            $list = $p->payload['refunds'] ?? [['amount' => $p->refunded_bdt, 'ref' => $p->refund_ref, 'reason' => '', 'at' => $p->updated_at?->toIso8601String()]];
            foreach ($list as $r) {
                $at = isset($r['at']) ? Carbon::parse($r['at']) : $p->updated_at;
                if ($at && $at->betweenIncluded($from->copy()->startOfDay(), $to->copy()->endOfDay())) {
                    $rows[] = [$at->toDateTimeString(), $p->order_id, $p->gateway, self::trxId($p), (float) ($r['amount'] ?? 0), $r['ref'] ?? '', $r['reason'] ?? ''];
                }
            }
        });
        usort($rows, fn ($a, $b) => strcmp($a[0], $b[0]));

        return [['refunded_at', 'order_id', 'gateway', 'original_trx_id', 'amount_bdt', 'refund_ref', 'reason'], $rows];
    }

    /** Points, wallet, gift card and affiliate liabilities right now. @return array<string, array{label: string, amount: float, note: string}> */
    public function liabilities(): array
    {
        $block = max(1, (int) config('retail.points.redeem_block', 100));
        $blockBdt = (float) config('retail.points.redeem_block_bdt', 50);
        $points = (int) DB::table('users')->sum('points_balance');
        $out = ['points' => ['label' => 'Skydrop Points', 'amount' => round($points / $block * $blockBdt, 2), 'note' => number_format($points).' points outstanding']];

        if (Schema::hasColumn('users', 'wallet_balance_bdt')) {
            $out['wallet'] = ['label' => 'Store credit wallets', 'amount' => round((float) DB::table('users')->sum('wallet_balance_bdt'), 2), 'note' => 'users.wallet_balance_bdt'];
        }
        if (Schema::hasTable('gift_cards')) {
            $q = DB::table('gift_cards')->where('status', 'active');
            $out['gift_cards'] = ['label' => 'Gift cards (unredeemed)', 'amount' => round((float) $q->sum('balance_bdt'), 2), 'note' => $q->count().' active cards'];
        }
        if (Schema::hasTable('affiliate_commissions')) {
            $q = DB::table('affiliate_commissions')->whereIn('status', ['pending', 'approved']);
            $approved = (float) DB::table('affiliate_commissions')->where('status', 'approved')->sum('amount_bdt');
            $out['affiliates'] = ['label' => 'Affiliate commissions payable', 'amount' => round($approved, 2),
                'note' => 'approved; plus ৳'.number_format((float) (clone $q)->where('status', 'pending')->sum('amount_bdt')).' pending (on hold)'];
        }

        return $out;
    }

    public function affiliatePayable(): array
    {
        if (! Schema::hasTable('affiliate_commissions')) {
            return [['note'], [['affiliate_commissions table not present']]];
        }
        $rows = DB::table('affiliate_commissions')->whereIn('status', ['pending', 'approved'])->orderBy('affiliate_id')->get()
            ->map(fn ($c) => [$c->affiliate_id, $c->order_id, $c->status, (float) $c->base_bdt, (float) $c->rate, (float) $c->amount_bdt, $c->approve_after, $c->approved_at])->all();

        return [['affiliate_id', 'order_id', 'status', 'base_bdt', 'rate_pct', 'amount_bdt', 'approve_after', 'approved_at'], $rows];
    }

    public function giftCards(): array
    {
        if (! Schema::hasTable('gift_cards')) {
            return [['note'], [['gift_cards table not present']]];
        }
        $rows = DB::table('gift_cards')->whereIn('status', ['active'])->get()
            ->map(fn ($g) => ['****'.substr((string) $g->code, -4), (float) $g->initial_bdt, (float) $g->balance_bdt, $g->status, $g->created_at])->all();

        return [['code', 'initial_bdt', 'balance_bdt', 'status', 'issued_at'], $rows];
    }

    /** VAT placeholder: vat_percent of the service margin (or revenue) per month. */
    public function vatSummary(Carbon $from, Carbon $to): array
    {
        $pct = Knobs::float('finance.vat_percent', 0);
        $on = (string) Knobs::get('finance.vat_on', 'margin');
        $report = $this->profit->report($from, $to);
        $byMonth = [];
        foreach ($report['orders'] as $r) {
            $m = substr((string) $r['date'], 0, 7);
            $byMonth[$m]['revenue'] = ($byMonth[$m]['revenue'] ?? 0) + $r['revenue'];
            $byMonth[$m]['margin'] = ($byMonth[$m]['margin'] ?? 0) + ($r['revenue'] - $r['cogs']);
        }
        $rows = [];
        foreach ($byMonth as $m => $v) {
            $base = $on === 'revenue' ? $v['revenue'] : max(0, $v['margin']);
            $rows[] = [$m, round($v['revenue'], 2), round($v['margin'], 2), $on, $pct, round($base * $pct / 100, 2)];
        }

        return [['month', 'revenue_bdt', 'margin_bdt', 'vat_base', 'vat_pct', 'vat_bdt'], $rows];
    }

    /** @return array<string, mixed> */
    public function monthClose(string $month): array
    {
        $from = Carbon::parse($month.'-01')->startOfMonth();
        $to = $from->copy()->endOfMonth();
        $report = $this->profit->report($from, $to);
        $refunds = array_sum(array_column($this->refunds($from, $to)[1], 4));

        return [
            'month' => $from->format('F Y'),
            'kpis' => $report['kpis'],
            'gateways' => $this->paymentsByGateway($from, $to),
            'refunds' => round($refunds, 2),
            'liabilities' => $this->liabilities(),
            'vat' => $this->vatSummary($from, $to)[1],
            'estimated_orders' => $report['orders']->where('estimated', true)->count(),
        ];
    }

    public static function trxId(Payment $p): string
    {
        $pl = $p->payload ?? [];

        return (string) ($p->gateway_ref ?: ($pl['trxID'] ?? $pl['bank_tran_id'] ?? $pl['tran_id'] ?? ''));
    }
}

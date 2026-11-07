<?php

namespace App\Services\Money;

use App\Support\Features;

/**
 * Card EMI through SSLCommerz (emi_option=1). The bank charge (a % of the financed amount, per bank and tenure)
 * is paid by the customer and shown up front; it is added to the order total as emi_charge_bdt.
 *
 * TODO(bkash-pay-later): bKash "Pay Later" / BNPL needs separate merchant approval from bKash. Add it as another
 * EMI-like option here (and a gateway flag) once the merchant account is approved.
 */
class EmiService
{
    public function enabled(): bool
    {
        return Features::enabled('pay.emi') && (bool) MoneySettings::get('emi.enabled', true);
    }

    public function minOrderBdt(): int
    {
        return MoneySettings::int('emi.min_order_bdt');
    }

    /** @return list<int> */
    public function tenures(): array
    {
        $t = array_map('intval', (array) MoneySettings::get('emi.tenures', [3, 6, 9, 12]));
        sort($t);

        return array_values(array_unique(array_filter($t, fn ($x) => in_array($x, [3, 6, 9, 12, 18, 24, 36], true))));
    }

    /** @return list<array{code: string, name: string, rates: array<string, float>}> */
    public function banks(): array
    {
        $out = [];
        foreach ((array) MoneySettings::get('emi.banks', []) as $b) {
            if (empty($b['code']) || empty($b['name'])) {
                continue;
            }
            $rates = [];
            foreach ((array) ($b['rates'] ?? []) as $t => $r) {
                if (is_numeric($r)) {
                    $rates[(string) (int) $t] = (float) $r;
                }
            }
            $out[] = ['code' => (string) $b['code'], 'name' => (string) $b['name'], 'rates' => $rates];
        }

        return $out;
    }

    /** Public config for clients (product page "EMI from ৳X/month", checkout tenure picker). */
    public function config(): array
    {
        $tenures = $this->tenures();

        return [
            'enabled' => $this->enabled(),
            'minOrderBdt' => $this->minOrderBdt(),
            'tenures' => $tenures,
            'banks' => array_map(fn ($b) => ['code' => $b['code'], 'name' => $b['name'],
                'rates' => (object) array_intersect_key($b['rates'], array_flip(array_map('strval', $tenures)))], $this->banks()),
        ];
    }

    public function eligible(float $amount): bool
    {
        return $this->enabled() && $amount >= $this->minOrderBdt() && $this->tenures() !== [];
    }

    /**
     * EMI maths for one bank + tenure on $amount (the amount financed on the card).
     *
     * @return array{eligible: bool, reason: ?string, tenure: int, bank: ?string, bankName: ?string, ratePct: float, chargeBdt: int, totalBdt: int, monthlyBdt: int}
     */
    public function plan(float $amount, int $tenure, ?string $bank = null): array
    {
        $base = ['eligible' => false, 'reason' => null, 'tenure' => $tenure, 'bank' => $bank, 'bankName' => null, 'ratePct' => 0.0,
            'chargeBdt' => 0, 'totalBdt' => (int) round($amount), 'monthlyBdt' => 0];
        if (! $this->enabled()) {
            return ['reason' => 'EMI is not available right now.'] + $base;
        }
        if ($amount < $this->minOrderBdt()) {
            return ['reason' => 'EMI is available on orders of ৳'.number_format($this->minOrderBdt()).' or more.'] + $base;
        }
        if (! in_array($tenure, $this->tenures(), true)) {
            return ['reason' => 'Choose one of the available EMI tenures.'] + $base;
        }
        $banks = collect($this->banks());
        $b = $bank ? $banks->firstWhere('code', $bank) : $banks->sortBy(fn ($x) => $x['rates'][(string) $tenure] ?? INF)->first();
        if ($bank && ! $b) {
            return ['reason' => 'This bank does not offer EMI here.'] + $base;
        }
        $rate = $b ? ($b['rates'][(string) $tenure] ?? null) : 0.0;
        if ($rate === null) {
            return ['reason' => $b['name'].' does not offer a '.$tenure.'-month EMI.'] + $base;
        }
        $charge = (int) ceil($amount * $rate / 100);
        $total = (int) round($amount) + $charge;

        return ['eligible' => true, 'reason' => null, 'tenure' => $tenure, 'bank' => $b['code'] ?? null, 'bankName' => $b['name'] ?? null,
            'ratePct' => (float) $rate, 'chargeBdt' => $charge, 'totalBdt' => $total, 'monthlyBdt' => (int) ceil($total / $tenure)];
    }

    /** Lowest monthly instalment for $amount over all banks/tenures, or null when not eligible. */
    public function fromMonthly(float $amount): ?int
    {
        if (! $this->eligible($amount)) {
            return null;
        }
        $best = null;
        foreach ($this->tenures() as $t) {
            foreach ($this->banks() ?: [['code' => null, 'rates' => [(string) $t => 0]]] as $b) {
                if (! isset($b['rates'][(string) $t])) {
                    continue;
                }
                $m = (int) ceil(($amount * (1 + $b['rates'][(string) $t] / 100)) / $t);
                $best = $best === null ? $m : min($best, $m);
            }
        }

        return $best;
    }
}

<?php

namespace App\Services\Fraud;

use App\Models\CourierCheck;
use App\Models\Order;
use App\Models\SupportRequest;
use App\Models\User;
use App\Services\Commerce\Phone;
use App\Services\Fraud\Couriers\CarryBeeDriver;
use App\Services\Fraud\Couriers\CourierFraudDriver;
use App\Services\Fraud\Couriers\PathaoDriver;
use App\Services\Fraud\Couriers\RedxDriver;
use App\Services\Fraud\Couriers\SteadfastDriver;
use App\Services\Security\Blocklist;
use App\Support\Knobs;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Order / phone risk score 0-100 from courier delivery history (Steadfast, Pathao, RedX, CarryBee) and internal
 * signals. score() never throws; courier failures are recorded as reasons-free errors.
 */
class FraudService
{
    public const DRIVERS = [SteadfastDriver::class, PathaoDriver::class, RedxDriver::class, CarryBeeDriver::class];

    public const HIGH_VALUE_BDT = 20000;

    /**
     * @return array{score: int, level: string, reasons: array<int, string>, couriers: array<string, array<string, mixed>>,
     *               courier_total: int, courier_success: ?float}
     */
    public function score(Order|string $subject, bool $withCouriers = true): array
    {
        $order = $subject instanceof Order ? $subject : null;
        $phone = Phone::normalize($order ? ($order->address['phone'] ?? $order->user?->phone) : $subject) ?? (string) $subject;
        $user = $order?->user ?? User::query()->where('phone', $phone)->first();

        $points = 0;
        $reasons = [];
        $add = function (int $p, string $why) use (&$points, &$reasons) {
            $points += $p;
            $reasons[] = ($p > 0 ? '+' : '').$p.' '.$why;
        };

        // ---- blocklist ----
        if (Blocklist::isBlocked('phone', $phone)) {
            $add(100, 'phone is on the blocklist');
        }
        if ($order?->client_ip && Blocklist::isBlocked('ip', $order->client_ip)) {
            $add(60, 'IP is on the blocklist');
        }
        if ($order?->device_id && Blocklist::isBlocked('device', $order->device_id)) {
            $add(60, 'device is on the blocklist');
        }

        // ---- courier history ----
        $couriers = $withCouriers ? $this->couriers($phone) : $this->cachedCouriers($phone);
        $total = array_sum(array_column($couriers, 'total'));
        $delivered = array_sum(array_column($couriers, 'delivered'));
        $cancelled = array_sum(array_column($couriers, 'cancelled'));
        $ratio = $total > 0 ? $delivered / max(1, $delivered + $cancelled) : null;
        if ($ratio !== null && $total >= 3) {
            if ($ratio < 0.5) {
                $add(40, sprintf('courier success %d%% (%d of %d parcels)', $ratio * 100, $delivered, $total));
            } elseif ($ratio < 0.75) {
                $add(20, sprintf('courier success %d%% (%d of %d parcels)', $ratio * 100, $delivered, $total));
            } elseif ($ratio >= 0.9 && $total >= 5) {
                $add(-15, sprintf('good courier history %d%% of %d parcels', $ratio * 100, $total));
            }
        }

        // ---- our own history ----
        if ($user) {
            $past = Order::query()->where('user_id', $user->id)->when($order, fn ($q) => $q->whereKeyNot($order->id));
            $cancels = (clone $past)->where('status', 'cancelled')->count();
            $delivered = (clone $past)->where('status', 'delivered')->count();
            if ($cancels >= 2) {
                $add(min(30, $cancels * 8), "{$cancels} cancelled orders before");
            }
            $returns = SupportRequest::query()->where('user_id', $user->id)->where('type', 'return')->count();
            if ($returns >= 2) {
                $add(10, "{$returns} return requests before");
            }
            if ($delivered >= 2 && $cancels === 0) {
                $add(-10, "{$delivered} delivered orders, none cancelled");
            }

            // new account + high value + 50% advance
            $isNew = $user->created_at && $user->created_at->gt(now()->subDay());
            $value = (float) ($order?->total_bdt ?? 0);
            if ($order && $isNew && $value >= self::HIGH_VALUE_BDT) {
                $add($order->payment_method === 'advance50' ? 25 : 10, 'new account with a high-value order'.($order->payment_method === 'advance50' ? ' on 50% advance' : ''));
            }

            // velocity
            $lastHour = Order::query()->where('user_id', $user->id)->where('created_at', '>=', now()->subHour())->count();
            if ($lastHour >= 3) {
                $add(15, "{$lastHour} orders in the last hour");
            }

            // disposable email
            $domain = strtolower((string) substr(strrchr((string) $user->email, '@') ?: '', 1));
            if ($domain !== '' && in_array($domain, (array) Knobs::get('finance.fraud.disposable_domains', []), true)) {
                $add(10, 'disposable email domain');
            }
        }

        if ($order) {
            // many accounts on one IP / device (30 days)
            foreach (['client_ip' => 'IP', 'device_id' => 'device'] as $col => $label) {
                if ($order->{$col}) {
                    $accounts = Order::query()->where($col, $order->{$col})->where('created_at', '>=', now()->subDays(30))->distinct()->count('user_id');
                    if ($accounts >= 3) {
                        $add(15, "{$accounts} accounts ordered from this {$label}");
                    }
                }
            }
            if ($order->client_ip) {
                $ipHour = Order::query()->where('client_ip', $order->client_ip)->where('created_at', '>=', now()->subHour())->count();
                if ($ipHour >= 5) {
                    $add(15, "{$ipHour} orders from this IP in the last hour");
                }
            }
            // address mismatch: delivery phone is not the account phone
            $addrPhone = Phone::normalize($order->address['phone'] ?? null);
            if ($user?->phone && $addrPhone && $addrPhone !== $user->phone) {
                $add(5, 'delivery phone differs from the account phone');
            }
        }

        // many accounts on one phone + name-phone mismatch with history
        $names = Order::query()->where('address->phone', $phone)->when($order, fn ($q) => $q->whereKeyNot($order->id))
            ->limit(50)->get(['address', 'user_id']);
        $accounts = $names->pluck('user_id')->unique()->count() + ($user && ! $names->pluck('user_id')->contains($user->id) && $names->isNotEmpty() ? 1 : 0);
        if ($accounts >= 3) {
            $add(10, "{$accounts} accounts used this phone");
        }
        $distinctNames = $names->map(fn ($o) => mb_strtolower(trim((string) ($o->address['name'] ?? ''))))->filter()->unique();
        $currentName = mb_strtolower(trim((string) ($order?->address['name'] ?? '')));
        if ($currentName !== '' && $distinctNames->isNotEmpty() && ! $distinctNames->contains($currentName) && $distinctNames->count() >= 2) {
            $add(10, 'name differs from earlier orders on this phone');
        }

        $score = max(0, min(100, $points));

        return [
            'score' => $score, 'level' => self::level($score), 'reasons' => $reasons, 'couriers' => $couriers,
            'courier_total' => $total, 'courier_success' => $ratio !== null ? round($ratio, 3) : null,
        ];
    }

    public static function level(int $score): string
    {
        return match (true) {
            $score >= (int) Knobs::get('finance.fraud.high_at', 70) => 'high',
            $score >= (int) Knobs::get('finance.fraud.medium_at', 40) => 'medium',
            default => 'low',
        };
    }

    /** Scores an order and stores the result. High risk → "Needs review" (no automatic verification). */
    public function scoreOrder(Order $order, bool $withCouriers = true): array
    {
        $r = $this->score($order, $withCouriers);
        $order->forceFill([
            'fraud_score' => $r['score'], 'fraud_level' => $r['level'], 'fraud_reasons' => $r['reasons'],
            'fraud_checked_at' => now(), 'fraud_review' => $order->fraud_review || $r['level'] === 'high',
        ])->saveQuietly();

        return $r;
    }

    /** @return array<string, array<string, mixed>> courier id => {total, delivered, cancelled, cached, error?} */
    public function couriers(string $phone): array
    {
        $local = '0'.substr((string) Phone::normalize($phone), 4);
        $hash = $this->phoneHash($phone);
        $days = (int) Knobs::get('finance.fraud.cache_days', 7);
        $out = [];
        foreach ($this->drivers() as $driver) {
            if (! $driver->enabled()) {
                continue;
            }
            $row = CourierCheck::query()->where('phone_hash', $hash)->where('courier', $driver->id())->first();
            if ($row && $row->checked_at->gt(now()->subDays($days)) && ! $row->error) {
                $out[$driver->id()] = ['total' => $row->total, 'delivered' => $row->delivered, 'cancelled' => $row->cancelled, 'cached' => true];

                continue;
            }
            try {
                $r = $driver->check($local);
                CourierCheck::query()->updateOrCreate(['phone_hash' => $hash, 'courier' => $driver->id()], [
                    'total' => $r['total'], 'delivered' => $r['delivered'], 'cancelled' => $r['cancelled'], 'raw' => $r['raw'], 'error' => null, 'checked_at' => now(),
                ]);
                $out[$driver->id()] = ['total' => $r['total'], 'delivered' => $r['delivered'], 'cancelled' => $r['cancelled'], 'cached' => false];
            } catch (Throwable $e) {
                Log::info('[fraud] '.$driver->id().' check failed: '.$e->getMessage());
                $out[$driver->id()] = ['total' => 0, 'delivered' => 0, 'cancelled' => 0, 'cached' => false, 'error' => mb_substr($e->getMessage(), 0, 200)];
            }
        }

        return $out;
    }

    /** Courier rows already cached (no HTTP), for the synchronous checkout path. */
    public function cachedCouriers(string $phone): array
    {
        return CourierCheck::query()->where('phone_hash', $this->phoneHash($phone))->whereNull('error')
            ->where('checked_at', '>', now()->subDays((int) Knobs::get('finance.fraud.cache_days', 7)))->get()
            ->mapWithKeys(fn ($r) => [$r->courier => ['total' => $r->total, 'delivered' => $r->delivered, 'cancelled' => $r->cancelled, 'cached' => true]])->all();
    }

    /** @return array<int, CourierFraudDriver> */
    public function drivers(): array
    {
        return array_map(fn ($c) => app($c), self::DRIVERS);
    }

    public function phoneHash(string $phone): string
    {
        return hash_hmac('sha256', Phone::normalize($phone) ?? $phone, (string) config('app.key'));
    }
}

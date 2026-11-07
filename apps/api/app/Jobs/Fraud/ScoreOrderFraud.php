<?php

namespace App\Jobs\Fraud;

use App\Models\Order;
use App\Services\Fraud\FraudGate;
use App\Services\Fraud\FraudService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Queued on order placed: full fraud score (with courier lookups). High risk → "Needs review". */
class ScoreOrderFraud implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public string $orderId) {}

    public function handle(FraudService $fraud): void
    {
        if (! FraudGate::active()) {
            return;
        }
        $order = Order::query()->with('user')->find($this->orderId);
        if (! $order) {
            return;
        }
        $wasReview = (bool) $order->fraud_review;
        $r = $fraud->scoreOrder($order);
        if ($r['level'] === 'high' && ! $wasReview) {
            // Staff-only note (the timeline is shown to the customer).
            $order->forceFill(['staff_notes' => trim(($order->staff_notes ? $order->staff_notes."\n" : '')
                .'['.now()->format('d M H:i')."] Fraud check: score {$r['score']} (high). Needs review before verification.")])->saveQuietly();
        }
    }
}

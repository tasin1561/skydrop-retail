<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Commerce\OrderWorkflow;
use Illuminate\Console\Command;

// Dev helper: walks an order through the tracking stages like the web demo (one stage per step).
class SimulateTracking extends Command
{
    protected $signature = 'retail:simulate-tracking {order : Order id (SR-XXXXXX)} {--steps=1 : Stages to advance} {--all : Advance to delivered} {--delay=0 : Seconds between stages} {--rider=0 : Also send N rider location pings once out for delivery (fulfilment tracking map)}';

    protected $description = 'Advance an order through the Skydrop tracking stages (mock Skydrop)';

    public function handle(OrderWorkflow $workflow): int
    {
        $order = Order::query()->find(strtoupper($this->argument('order')));
        if (! $order) {
            $this->error('Order not found.');

            return self::FAILURE;
        }
        if (in_array($order->status, ['delivered', 'cancelled'], true)) {
            $this->warn("Order is already {$order->status}.");

            return self::SUCCESS;
        }
        $steps = $this->option('all') ? count(OrderWorkflow::FLOW) : max(0, (int) $this->option('steps')); // --steps=0 with --rider only pings
        for ($i = 0; $i < $steps; $i++) {
            $idx = array_search($order->status, OrderWorkflow::FLOW, true);
            $next = OrderWorkflow::FLOW[$idx + 1] ?? null;
            if (! $next) {
                break;
            }
            $source = in_array($next, ['placed', 'verified', 'purchased'], true) ? 'admin' : 'skydrop';
            $order = $workflow->transition($order, $next, $source, null);
            $this->info("{$order->id}: {$next}");
            if ($i < $steps - 1 && $this->option('delay') > 0) {
                sleep((int) $this->option('delay'));
            }
        }

        if ((int) $this->option('rider') > 0 && $order->status === 'out_for_delivery') {
            $n = app(\App\Services\Fulfilment\TrackingRecorder::class)->simulateRider($order, (int) $this->option('rider'));
            $this->info("{$order->id}: {$n} rider location ping(s)");
        }

        return self::SUCCESS;
    }
}

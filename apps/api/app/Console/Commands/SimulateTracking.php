<?php

namespace App\Console\Commands;

use App\Models\Order;
use App\Services\Commerce\OrderWorkflow;
use Illuminate\Console\Command;

// Dev helper: walks an order through the tracking stages like the web demo (one stage per step).
class SimulateTracking extends Command
{
    protected $signature = 'retail:simulate-tracking {order : Order id (SR-XXXXXX)} {--steps=1 : Stages to advance} {--all : Advance to delivered} {--delay=0 : Seconds between stages}';

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
        $steps = $this->option('all') ? count(OrderWorkflow::FLOW) : max(1, (int) $this->option('steps'));
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

        return self::SUCCESS;
    }
}

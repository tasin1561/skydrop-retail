<?php

namespace App\Console\Commands;

use App\Models\ApiLog;
use App\Services\Catalog\BudgetGuard;
use Illuminate\Console\Command;

class CatalogBudget extends Command
{
    protected $signature = 'retail:budget';

    protected $description = "Show this month's provider spend against the Oxylabs budget (and send threshold alerts)";

    public function handle(BudgetGuard $guard): int
    {
        $s = $guard->check();
        $this->info(sprintf('Oxylabs: $%.4f of $%.2f (%d%%)%s', $s['spent'], $s['budget'], $s['pct'], $s['alerted'] ? " - alert sent at {$s['alerted']}%" : ''));
        $rows = ApiLog::query()->where('created_at', '>=', now()->startOfMonth())
            ->selectRaw('provider, type, count(*) as calls, sum(cost_usd) as cost, avg(ms) as avg_ms')
            ->groupBy('provider', 'type')->orderBy('provider')->get()
            ->map(fn ($r) => [$r->provider, $r->type, $r->calls, sprintf('%.4f', $r->cost), (int) $r->avg_ms])->all();
        $this->table(['provider', 'type', 'calls', 'cost_usd', 'avg_ms'], $rows);

        return self::SUCCESS;
    }
}

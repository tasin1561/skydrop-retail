<?php

namespace App\Console\Commands\System;

use App\Services\System\HealthAlerts;
use App\Services\System\LogReader;
use App\Support\Knobs;
use Illuminate\Console\Command;

/** Scheduled every 5 minutes: alerts on red transitions (and recoveries) of the System → Health conditions. */
class HealthCheck extends Command
{
    protected $signature = 'retail:health-check {--prune-logs : Also delete log files older than the retention setting}';

    protected $description = 'Check DB, queue worker, failed jobs, provider circuits, SMS balance, disk and the Oxylabs daily cap; alert on changes';

    public function handle(HealthAlerts $alerts): int
    {
        $r = $alerts->run();
        foreach ($r['state'] as $name => $bad) {
            $this->line(sprintf('%-18s %s', $name, $bad ? '<fg=red>RED</>' : '<fg=green>ok</>'));
        }
        if ($r['alerts'] || $r['recoveries']) {
            $this->info('Alerts: '.implode(', ', $r['alerts'] ?: ['-']).' · recoveries: '.implode(', ', $r['recoveries'] ?: ['-']));
        }
        if ($this->option('prune-logs')) {
            $this->info('Deleted '.LogReader::prune((int) Knobs::get('system.log_retention_days', 14)).' old log files.');
        }

        return self::SUCCESS;
    }
}

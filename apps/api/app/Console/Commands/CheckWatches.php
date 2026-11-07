<?php

namespace App\Console\Commands;

use App\Jobs\Commerce\CheckProductWatches;
use App\Jobs\Commerce\ExpirePoints;
use Illuminate\Console\Command;

class CheckWatches extends Command
{
    protected $signature = 'retail:commerce-maintenance {--watches} {--points}';

    protected $description = 'Run price-drop/back-in-stock watches and/or expire loyalty points now';

    public function handle(): int
    {
        $all = ! $this->option('watches') && ! $this->option('points');
        if ($all || $this->option('watches')) {
            CheckProductWatches::dispatchSync();
            $this->info('Watches checked.');
        }
        if ($all || $this->option('points')) {
            ExpirePoints::dispatchSync();
            $this->info('Points expired.');
        }

        return self::SUCCESS;
    }
}

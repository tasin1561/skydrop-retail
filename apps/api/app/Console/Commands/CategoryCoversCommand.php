<?php

namespace App\Console\Commands;

use App\Services\Categories\CategoryCovers;
use Illuminate\Console\Command;

class CategoryCoversCommand extends Command
{
    protected $signature = 'retail:category-covers';

    protected $description = 'Pick cover photos for every category from its best products (an admin image still wins)';

    public function handle(CategoryCovers $covers): int
    {
        $this->info('Updated cover photos on '.$covers->refresh().' categories.');

        return self::SUCCESS;
    }
}

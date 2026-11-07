<?php

namespace App\Jobs\Shopping;

use App\Services\Shopping\ProductMatcher;
use App\Support\Features;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Daily (and from the admin): fills product_matches for "Compare stores". */
class MatchProducts implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    public function __construct(public ?string $brand = null) {}

    public function handle(ProductMatcher $matcher): void
    {
        if (! Features::enabled('compare_stores')) {
            return;
        }
        $matcher->run($this->brand);
    }
}

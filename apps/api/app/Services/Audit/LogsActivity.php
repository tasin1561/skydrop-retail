<?php

namespace App\Services\Audit;

/** Opt-in trait: `use LogsActivity;` on a model logs staff create/update/delete with a diff. */
trait LogsActivity
{
    public static function bootLogsActivity(): void
    {
        static::observe(ActivityObserver::class);
    }
}

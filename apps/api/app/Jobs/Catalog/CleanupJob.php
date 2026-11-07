<?php

namespace App\Jobs\Catalog;

use App\Models\ApiLog;
use App\Models\FetchJob;
use App\Models\RawPage;
use App\Models\SearchCache;
use App\Services\Catalog\RetailSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/** Daily: drop old raw HTML, api_logs, finished fetch_jobs and search pages past their stale window. */
class CleanupJob implements ShouldQueue
{
    use Queueable;

    /** @return array<string, int> */
    public function handle(): array
    {
        return [
            'raw_pages' => RawPage::query()->where('fetched_at', '<', now()->subDays(RetailSettings::int('ttl.raw_html_days', 3)))->delete(),
            'api_logs' => ApiLog::query()->where('created_at', '<', now()->subDays(RetailSettings::int('ttl.api_logs_days', 90)))->delete(),
            'fetch_jobs' => FetchJob::query()->where('updated_at', '<', now()->subDays(7))->delete(),
            'search_cache' => SearchCache::query()->where('fetched_at', '<', now()->subMinutes(RetailSettings::ttl('search_stale')))->delete(),
        ];
    }
}

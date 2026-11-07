<?php

namespace App\Services\Catalog;

use App\Models\FetchJob;
use Closure;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * fetch_jobs rows behind GET /jobs/{id}. dispatch() de-duplicates: while a job for the same key is queued or
 * running (and younger than 10 minutes) callers get that job's id instead of a second provider call.
 */
class JobTracker
{
    /** @param Closure(string): ShouldQueue $make builds the queued job for the new fetch_jobs id */
    public function dispatch(string $type, string $key, Closure $make): string
    {
        $active = FetchJob::query()->where('key', $key)->where('type', $type)->whereIn('status', ['queued', 'running'])
            ->where('updated_at', '>', now()->subMinutes(10))->value('id');
        if ($active) {
            return $active;
        }
        $job = FetchJob::query()->create(['type' => $type, 'key' => $key, 'status' => 'queued']);
        dispatch($make($job->id));

        return $job->id;
    }

    public function running(?string $id): void
    {
        if ($id) {
            FetchJob::query()->whereKey($id)->update(['status' => 'running', 'attempts' => DB::raw('attempts + 1'), 'updated_at' => now()]);
        }
    }

    public function done(?string $id, mixed $result = null, ?string $provider = null): void
    {
        if ($id) {
            FetchJob::query()->whereKey($id)->first()?->update(['status' => 'done', 'result' => $result, 'provider_used' => $provider, 'error' => null]);
        }
    }

    public function failed(?string $id, string $error): void
    {
        if ($id) {
            FetchJob::query()->whereKey($id)->update(['status' => 'failed', 'error' => mb_substr($error, 0, 1000), 'updated_at' => now()]);
        }
    }

    public function active(string $type, string $key): ?string
    {
        return FetchJob::query()->where('key', $key)->where('type', $type)->whereIn('status', ['queued', 'running'])->value('id');
    }
}

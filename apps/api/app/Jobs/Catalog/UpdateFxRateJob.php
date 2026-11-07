<?php

namespace App\Jobs\Catalog;

use App\Models\FxRate;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * INR->BDT rate. With FX_FEED_URL set it reads {"rate": <BDT per INR>} (or {"rates":{"BDT":..}} with INR base);
 * otherwise it is a no-op and the rate stays manual (admin / FX_INR_BDT).
 */
class UpdateFxRateJob implements ShouldQueue
{
    use Queueable;

    public function handle(): ?float
    {
        $url = config('retail.fx_feed_url');
        if (! $url) {
            return null;
        }
        try {
            $j = Http::timeout(15)->acceptJson()->get($url)->throw()->json();
            $rate = (float) ($j['rate'] ?? $j['rates']['BDT'] ?? 0);
            if ($rate < 0.5 || $rate > 5) {
                throw new \RuntimeException("implausible INR/BDT rate $rate");
            }
            FxRate::query()->create(['pair' => 'INRBDT', 'rate' => $rate, 'source' => parse_url($url, PHP_URL_HOST) ?: 'feed', 'fetched_at' => now()]);
            Cache::forget('fx:INRBDT');

            return $rate;
        } catch (Throwable $e) {
            Log::warning('FX update failed: '.$e->getMessage());

            return null;
        }
    }
}

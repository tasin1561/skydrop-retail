<?php

namespace App\Services\Tracking\Drivers;

use App\Services\Tracking\TrackingSettings;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

abstract class HttpDriver implements TrackingDriver
{
    protected function http(): PendingRequest
    {
        return Http::acceptJson()->asJson()->timeout((int) config('retail.tracking.timeout', 8))->connectTimeout(4);
    }

    /** Maps a response to the driver result; 429 / 5xx throw so the job retries with backoff. */
    protected function result(Response $r, ?string $bodyError = null): array
    {
        $code = $r->status();
        if ($code === 429 || $code >= 500) {
            throw new RuntimeException("{$this->name()} HTTP {$code}: ".mb_substr($r->body(), 0, 300));
        }
        if ($code >= 400) {
            return ['code' => $code, 'error' => mb_substr($r->body(), 0, 1000)];
        }

        return ['code' => $code, 'error' => $bodyError];
    }

    protected function setting(string $key): string
    {
        return TrackingSettings::str($key);
    }

    /** @return array<int, array{id: string, title: ?string, brand: ?string, category: ?string, price: ?float, qty: int}> */
    protected function items(array $e): array
    {
        return array_values($e['items'] ?? []);
    }
}

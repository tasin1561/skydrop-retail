<?php

namespace App\Sources\Support;

use App\Sources\Exceptions\ProductGoneException;
use App\Sources\Exceptions\SourceException;
use Illuminate\Http\Client\PendingRequest;
use Throwable;

/** GET-a-JSON-endpoint helper with api_logs logging for the Amazon fallbacks. */
trait HttpJsonSource
{
    abstract protected function http(): PendingRequest;

    /**
     * @param  array<string, mixed>  $query
     * @return array<string, mixed>
     */
    protected function getJson(string $url, array $query, string $type, string $key): array
    {
        $t = microtime(true);
        try {
            $r = $this->http()->get($url, $query);
        } catch (Throwable $e) {
            app(ApiLogger::class)->log($this->name(), $type, 'amazon', $key, 0, (int) ((microtime(true) - $t) * 1000), false, 0, $e->getMessage());
            throw new SourceException($this->name().' request failed: '.$e->getMessage(), 0, $e);
        }
        $ms = (int) ((microtime(true) - $t) * 1000);
        app(ApiLogger::class)->log($this->name(), $type, 'amazon', $key, $r->status(), $ms, false, 0, $r->successful() ? null : mb_substr($r->body(), 0, 300));
        if ($r->status() === 404) {
            throw new ProductGoneException("$key not found");
        }
        if (! $r->successful() || ! is_array($r->json())) {
            throw new SourceException($this->name()." HTTP {$r->status()}");
        }

        return $r->json();
    }
}

<?php

namespace App\Sources\Support;

use App\Services\Catalog\RetailSettings;
use App\Sources\Exceptions\ProductGoneException;
use App\Sources\Exceptions\SourceException;
use App\Support\Limits;
use GuzzleHttp\Handler\CurlMultiHandler;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Pool;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Oxylabs Web Scraper API (realtime). One POST per query; every attempt is logged with ms, status, rendered flag
 * and cost. Supports hedged requests (duplicate after hedge_after seconds, first success wins) and pools.
 */
class OxylabsClient
{
    public function __construct(private ApiLogger $logger) {}

    public function isConfigured(): bool
    {
        return (bool) (config('retail.oxylabs.token') || config('retail.oxylabs.username'));
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed> results[0] (content is a string for raw HTML, an array when parsed)
     */
    public function query(array $payload, string $type, string $market, string $key, bool $hedge = false): array
    {
        $hedgeAfter = RetailSettings::int('oxylabs.hedge_after');
        if (! $hedge || $hedgeAfter <= 0) {
            $t = microtime(true);
            try {
                $response = $this->request($payload)->post($this->url(), $payload);
            } catch (Throwable $e) {
                $this->logFailure($payload, $type, $market, $key, $t, $e);
                throw new SourceException('Oxylabs request failed: '.$e->getMessage(), 0, $e);
            }

            return $this->interpret($response, $payload, $type, $market, $key, $t);
        }

        return $this->hedged($payload, $type, $market, $key, $hedgeAfter);
    }

    /**
     * Concurrent queries; each value is results[0] or the Throwable for that key.
     *
     * @param  array<string, array<string, mixed>>  $payloads
     * @return array<string, array<string, mixed>|Throwable>
     */
    public function pool(array $payloads, string $type, string $market, ?int $concurrency = null): array
    {
        $concurrency ??= max(1, Limits::int('catalog.max_concurrent_fetches')); // System → Limits
        if ($payloads === []) {
            return [];
        }
        $t = microtime(true);
        $responses = Http::pool(function (Pool $pool) use ($payloads) {
            foreach ($payloads as $key => $payload) {
                $this->configure($pool->as((string) $key), $payload)->post($this->url(), $payload);
            }
        }, $concurrency);
        $out = [];
        foreach ($payloads as $key => $payload) {
            $r = $responses[$key] ?? null;
            try {
                if (! $r instanceof Response) {
                    $this->logFailure($payload, $type, $market, (string) $key, $t, $r instanceof Throwable ? $r : null);
                    throw new SourceException('Oxylabs request failed: '.($r instanceof Throwable ? $r->getMessage() : 'no response'));
                }
                $out[$key] = $this->interpret($r, $payload, $type, $market, (string) $key, $t);
            } catch (Throwable $e) {
                $out[$key] = $e;
            }
        }

        return $out;
    }

    /** @param array<string, mixed> $payload */
    private function hedged(array $payload, string $type, string $market, string $key, int $hedgeAfter): array
    {
        $handler = new CurlMultiHandler;
        $start = microtime(true);
        $deadline = $start + $this->timeout($payload) + $hedgeAfter;
        /** @var array<int, array{promise: PromiseInterface, t: float, retry: bool}> $inflight */
        $inflight = [['promise' => $this->async($handler, $payload), 't' => $start, 'retry' => false]];
        $fired = 1;
        $lastError = null;

        while (true) {
            $handler->tick();
            foreach ($inflight as $i => $req) {
                if ($req['promise']->getState() === PromiseInterface::PENDING) {
                    continue;
                }
                unset($inflight[$i]);
                try {
                    $result = $req['promise']->wait();
                } catch (Throwable $e) {
                    $result = $e;
                }
                try {
                    if (! $result instanceof Response) {
                        // No answer at all (connection error): still one live call, logged as failed.
                        UsageContext::run(null, $req['retry'], fn () => $this->logFailure($payload, $type, $market, $key, $req['t'], $result instanceof Throwable ? $result : null));
                        throw new SourceException('Oxylabs request failed: '.($result instanceof Throwable ? $result->getMessage() : 'no response'));
                    }
                    // The second request (hedge duplicate or retry after a fast failure) is logged as a retry.
                    $winner = UsageContext::run(null, $req['retry'], fn () => $this->interpret($result, $payload, $type, $market, $key, $req['t']));
                    // The losing duplicate is still billed by Oxylabs when it completes: count it.
                    foreach ($inflight as $loser) {
                        $this->logger->log('oxylabs', $type, $market, $key, 499, (int) ((microtime(true) - $loser['t']) * 1000),
                            $this->rendered($payload), ApiLogger::oxylabsCost($market, $this->rendered($payload)), 'hedge duplicate abandoned', true);
                    }

                    return $winner;
                } catch (ProductGoneException $e) {
                    throw $e;
                } catch (Throwable $e) {
                    $lastError = $e;
                }
            }
            $now = microtime(true);
            if ($fired === 1 && $now - $start >= $hedgeAfter) {
                $inflight[] = ['promise' => $this->async($handler, $payload), 't' => $now, 'retry' => true];
                $fired++;
            }
            if ($inflight === [] && ($fired > 1 || $lastError)) {
                if ($fired === 1) { // first failed fast: fire the duplicate as a retry
                    $inflight[] = ['promise' => $this->async($handler, $payload), 't' => $now, 'retry' => true];
                    $fired++;

                    continue;
                }
                throw $lastError instanceof SourceException ? $lastError : new SourceException('Oxylabs hedged request failed', 0, $lastError);
            }
            if ($now > $deadline) {
                $this->logger->log('oxylabs', $type, $market, $key, 408, (int) (($now - $start) * 1000), $this->rendered($payload), 0, 'hedged timeout');
                throw new SourceException('Oxylabs hedged request timed out');
            }
            usleep(5_000);
        }
    }

    /** @param array<string, mixed> $payload */
    private function async(CurlMultiHandler $handler, array $payload): PromiseInterface
    {
        $promise = $this->request($payload)->setHandler($handler)->async()->post($this->url(), $payload);
        if (method_exists($promise, 'buildPromise')) {
            $promise = $promise->buildPromise();
        }

        return $promise;
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    private function interpret(Response $response, array $payload, string $type, string $market, string $key, float $t): array
    {
        $ms = (int) ((microtime(true) - $t) * 1000);
        $rendered = $this->rendered($payload);
        if (! $response->successful()) {
            $this->logger->log('oxylabs', $type, $market, $key, $response->status(), $ms, $rendered, 0, mb_substr($response->body(), 0, 300));
            throw new SourceException("Oxylabs HTTP {$response->status()}");
        }
        $result = $response->json('results.0');
        $status = (int) ($result['status_code'] ?? 0);
        $this->logger->log('oxylabs', $type, $market, $key, $status ?: 200, $ms, $rendered,
            ApiLogger::oxylabsCost($market, $rendered), $status >= 400 ? "target status $status" : null);
        if ($status === 404) {
            throw new ProductGoneException("$market $key not found");
        }
        if (! is_array($result) || $status >= 400 || ! isset($result['content'])) {
            throw new SourceException("Oxylabs target status $status");
        }

        return $result;
    }

    /** @param array<string, mixed> $payload */
    private function logFailure(array $payload, string $type, string $market, string $key, float $t, ?Throwable $e): void
    {
        $this->logger->log('oxylabs', $type, $market, $key, 0, (int) ((microtime(true) - $t) * 1000), $this->rendered($payload), 0, $e?->getMessage() ?? 'failed');
    }

    /** @param array<string, mixed> $payload */
    private function rendered(array $payload): bool
    {
        return ($payload['render'] ?? null) === 'html';
    }

    private function url(): string
    {
        return (string) config('retail.oxylabs.base_url');
    }

    /** @param array<string, mixed> $payload */
    private function request(array $payload = []): PendingRequest
    {
        return $this->configure(Http::createPendingRequest(), $payload);
    }

    /** Seconds Oxylabs gets for one job: rendered jobs (a real browser) take longer (Myntra listings 10-30 s). @param array<string, mixed> $payload */
    private function timeout(array $payload): int
    {
        return $this->rendered($payload) ? (int) config('retail.oxylabs.render_timeout', 60) : (int) config('retail.oxylabs.timeout', 25);
    }

    /** @param array<string, mixed> $payload */
    private function configure(PendingRequest $r, array $payload = []): PendingRequest
    {
        $token = config('retail.oxylabs.token');
        $r = $token ? $r->withToken($token) : $r->withBasicAuth((string) config('retail.oxylabs.username'), (string) config('retail.oxylabs.password'));

        return $r->acceptJson()->asJson()->timeout($this->timeout($payload) + 5)->connectTimeout(10);
    }
}

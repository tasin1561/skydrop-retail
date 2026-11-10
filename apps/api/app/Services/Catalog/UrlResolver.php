<?php

namespace App\Services\Catalog;

use App\Sources\Parsers\FlipkartHtmlParser;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * "Paste any link": turns an amazon.in / flipkart.com / bigbasket.com URL (including amzn.in, amzn.to, a.co and
 * fkrt.it short links, which are followed without fetching the target page) into [market, our product id].
 */
class UrlResolver
{
    private const SHORT_HOSTS = ['amzn.in', 'amzn.to', 'a.co', 'amzn.eu', 'fkrt.it', 'fkrt.co', 'dl.flipkart.com'];

    /** @return array{market: string, id: string}|null */
    public function resolve(string $url): ?array
    {
        $url = trim($url);
        if (! preg_match('#^https?://#i', $url)) {
            $url = 'https://'.$url;
        }
        for ($hop = 0; $hop < 4; $hop++) {
            $host = strtolower((string) parse_url($url, PHP_URL_HOST));
            $host = preg_replace('/^(www|m|smile)\./', '', $host) ?? $host;
            if ($found = $this->match($host, $url)) {
                return $found;
            }
            if (! in_array($host, self::SHORT_HOSTS, true) || ! ($url = $this->redirect($url))) {
                return null;
            }
        }

        return null;
    }

    public function isSupportedHost(string $url): bool
    {
        $host = strtolower((string) parse_url(preg_match('#^https?://#i', $url) ? $url : 'https://'.$url, PHP_URL_HOST));
        $host = preg_replace('/^(www|m|smile)\./', '', $host) ?? $host;

        return in_array($host, ['amazon.in', 'flipkart.com', 'bigbasket.com', ...self::SHORT_HOSTS], true);
    }

    /** @return array{market: string, id: string}|null */
    private function match(string $host, string $url): ?array
    {
        $path = (string) parse_url($url, PHP_URL_PATH);
        if ($host === 'amazon.in' && preg_match('#/(?:dp|gp/product|gp/aw/d|product|exec/obidos/asin|o/ASIN)/([A-Z0-9]{10})(?:[/?]|$)#i', $path.'/', $m)) {
            return ['market' => 'amazon', 'id' => strtoupper($m[1])];
        }
        if ($host === 'flipkart.com' && ($id = FlipkartHtmlParser::idFromUrl($url))) {
            return ['market' => 'flipkart', 'id' => $id]; // keeps ?pid= so the shared variant opens
        }
        if ($host === 'bigbasket.com' && preg_match('#/pd/(\d+)#', $path, $m)) {
            return ['market' => 'bigbasket', 'id' => 'bb'.$m[1]];
        }

        return null;
    }

    private function redirect(string $url): ?string
    {
        try {
            $r = Http::withoutRedirecting()->timeout(8)->withHeaders(['User-Agent' => 'Mozilla/5.0 (SkydropRetail link resolver)'])->get($url);
            $loc = $r->header('Location');
            if (! $loc) {
                return null;
            }

            return str_starts_with($loc, '/') ? preg_replace('#^(https?://[^/]+).*$#', '$1', $url).$loc : $loc;
        } catch (Throwable) {
            return null;
        }
    }
}

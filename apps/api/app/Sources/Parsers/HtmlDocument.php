<?php

namespace App\Sources\Parsers;

use App\Sources\Support\Text;
use DOMDocument;
use DOMNode;
use DOMXPath;

/** Tiny DOM wrapper used by the best-effort Flipkart / BigBasket parsers. */
final class HtmlDocument
{
    public readonly DOMXPath $x;

    public function __construct(public readonly string $html)
    {
        $dom = new DOMDocument;
        $prev = libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="UTF-8">'.$html, LIBXML_NOERROR | LIBXML_NOWARNING | LIBXML_COMPACT | LIBXML_PARSEHUGE);
        libxml_clear_errors();
        libxml_use_internal_errors($prev);
        $this->x = new DOMXPath($dom);
    }

    public function text(string $q, ?DOMNode $ctx = null): string
    {
        $n = $this->x->query($q, $ctx)?->item(0);

        return $n ? Text::clean($n->textContent) : '';
    }

    /** All JSON-LD blocks, flattened (@graph expanded). @return list<array<string, mixed>> */
    public function jsonLd(): array
    {
        $out = [];
        foreach ($this->x->query('//script[@type="application/ld+json"]') ?: [] as $s) {
            $data = json_decode(trim($s->textContent), true);
            if (! is_array($data)) {
                continue;
            }
            $list = array_is_list($data) ? $data : [$data];
            foreach ($list as $d) {
                if (! is_array($d)) {
                    continue;
                }
                foreach (isset($d['@graph']) && is_array($d['@graph']) ? $d['@graph'] : [$d] as $node) {
                    if (is_array($node)) {
                        $out[] = $node;
                    }
                }
            }
        }

        return $out;
    }

    /** @return array<string, mixed>|null */
    public function jsonLdOfType(string $type): ?array
    {
        foreach ($this->jsonLd() as $n) {
            $t = $n['@type'] ?? null;
            if ($t === $type || (is_array($t) && in_array($type, $t, true))) {
                return $n;
            }
        }

        return null;
    }

    /** Decode `<script id="__NEXT_DATA__">` or `window.__INITIAL_STATE__ = {...};`. */
    public function embeddedState(): ?array
    {
        $next = $this->x->query('//script[@id="__NEXT_DATA__"]')?->item(0);
        if ($next) {
            $d = json_decode($next->textContent, true);
            if (is_array($d)) {
                return $d;
            }
        }
        if (preg_match('/window\.__INITIAL_STATE__\s*=\s*(\{.*?\});\s*<\/script>/s', $this->html, $m)) {
            $d = json_decode($m[1], true);

            return is_array($d) ? $d : null;
        }

        return null;
    }

    /**
     * Depth-first search for arrays that satisfy $match.
     *
     * @param  callable(array<mixed>): bool  $match
     * @return list<array<mixed>>
     */
    public static function find(mixed $node, callable $match, int $limit = 200, int $depth = 0): array
    {
        if (! is_array($node) || $depth > 40) {
            return [];
        }
        $out = $match($node) ? [$node] : [];
        foreach ($node as $child) {
            if (count($out) >= $limit) {
                break;
            }
            if (is_array($child)) {
                array_push($out, ...self::find($child, $match, $limit - count($out), $depth + 1));
            }
        }

        return $out;
    }
}

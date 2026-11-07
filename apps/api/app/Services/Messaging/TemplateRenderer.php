<?php

namespace App\Services\Messaging;

use App\Models\MessageTemplate;
use Illuminate\Support\Facades\Cache;
use Throwable;

/**
 * Looks up message_templates (falling back to the built-in defaults in Events) and renders
 * {{placeholder}}, {{#flag}}…{{/flag}} and {{^flag}}…{{/flag}}.
 */
class TemplateRenderer
{
    private const CACHE = 'messaging:templates:v1';

    /** @return array{subject: ?string, body: string, active: bool, locale: string}|null */
    public function find(string $key, string $channel, string $locale): ?array
    {
        foreach (array_unique([$locale, 'en']) as $loc) {
            $row = $this->rows()[$key.'|'.$channel.'|'.$loc] ?? null;
            if ($row !== null) {
                return $row + ['locale' => $loc];
            }
            $def = Events::defaultTemplate($key, $channel, $loc);
            if ($def !== null) {
                return $def + ['locale' => $loc];
            }
        }

        return null;
    }

    /** @return array{subject: ?string, body: string, locale: string}|null null when missing or inactive */
    public function render(string $key, string $channel, string $locale, array $data): ?array
    {
        $t = $this->find($key, $channel, $locale);
        if (! $t || ! $t['active']) {
            return null;
        }

        return [
            'subject' => $t['subject'] !== null ? $this->string($t['subject'], $data) : null,
            'body' => $this->string($t['body'], $data),
            'locale' => $t['locale'],
        ];
    }

    public function string(string $template, array $data): string
    {
        $data += ['brand' => config('messaging.brand.name', 'Skydrop Retail')];
        // Sections, innermost first so nesting works.
        $re = '/\{\{([#^])\s*([\w.]+)\s*\}\}((?:(?!\{\{[#^]).)*?)\{\{\/\s*\2\s*\}\}/s';
        for ($i = 0; $i < 10 && preg_match($re, $template); $i++) {
            $template = preg_replace_callback($re, function ($m) use ($data) {
                $v = data_get($data, $m[2]);
                $truthy = ! ($v === null || $v === false || $v === '' || $v === 0 || $v === '0' || $v === []);

                return ($m[1] === '#') === $truthy ? $m[3] : '';
            }, $template);
        }
        $out = preg_replace_callback('/\{\{\s*([\w.]+)\s*\}\}/', function ($m) use ($data) {
            $v = data_get($data, $m[1]);

            return is_scalar($v) ? (string) $v : '';
        }, $template);

        // Tidy: collapse doubled spaces left by empty placeholders, trim each line.
        $out = preg_replace('/[ \t]{2,}/', ' ', $out);
        $out = implode("\n", array_map('trim', explode("\n", $out)));

        return trim(preg_replace("/\n{3,}/", "\n\n", $out));
    }

    public function flush(): void
    {
        Cache::forget(self::CACHE);
    }

    /** @return array<string, array{subject: ?string, body: string, active: bool}> */
    private function rows(): array
    {
        try {
            return Cache::remember(self::CACHE, 600, fn () => MessageTemplate::query()->get()
                ->mapWithKeys(fn (MessageTemplate $t) => [$t->key.'|'.$t->channel.'|'.$t->locale => ['subject' => $t->subject, 'body' => $t->body, 'active' => (bool) $t->active]])
                ->all());
        } catch (Throwable) {
            return [];
        }
    }
}

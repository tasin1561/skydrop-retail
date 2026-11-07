<?php

namespace App\Services\Assistant;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\StreamInterface;

/**
 * Minimal Messages API client (POST /v1/messages) with our own SSE parser, so no SDK is needed.
 * Returns the assembled assistant message: content blocks, stop reason and token usage.
 */
class AnthropicClient
{
    /**
     * @param  callable(string): void  $onText  receives each text delta as it streams
     * @param  (callable(): bool)|null  $aborted  returns true when the shopper went away (stop reading)
     * @return array{content: list<array<string, mixed>>, stop_reason: ?string, model: string, usage: array{input: int, output: int, cache_read: int, cache_write: int}}
     */
    public function stream(array $payload, callable $onText, ?callable $aborted = null): array
    {
        $payload['stream'] = true;
        try {
            $response = Http::withHeaders([
                'x-api-key' => (string) config('assistant.api_key'),
                'anthropic-version' => (string) config('assistant.version', '2023-06-01'),
                'content-type' => 'application/json',
                'accept' => 'text/event-stream',
            ])->withOptions(['stream' => true])->timeout((int) config('assistant.timeout', 90))->connectTimeout(10)
                ->post(rtrim((string) config('assistant.base_url'), '/').'/v1/messages', $payload);
        } catch (ConnectionException $e) {
            throw new AnthropicException('Could not reach the Claude API', 0, 'connection');
        }

        if ($response->failed()) {
            $body = json_decode($response->body(), true);
            throw new AnthropicException((string) ($body['error']['message'] ?? 'Claude API error'), $response->status(), $body['error']['type'] ?? null);
        }

        return $this->parse($response->toPsrResponse()->getBody(), $onText, $aborted, (string) ($payload['model'] ?? ''));
    }

    /** @return array{content: list<array<string, mixed>>, stop_reason: ?string, model: string, usage: array{input: int, output: int, cache_read: int, cache_write: int}} */
    public function parse(StreamInterface $body, callable $onText, ?callable $aborted, string $model): array
    {
        $msg = ['content' => [], 'stop_reason' => null, 'model' => $model, 'usage' => ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0]];
        $json = []; // partial tool input per block index
        $buf = '';
        $first = true;

        while (! $body->eof()) {
            $chunk = $body->read(8192);
            if ($chunk === '') {
                continue;
            }
            $buf .= $chunk;
            if ($first) {
                $first = false;
                if (str_starts_with(ltrim($buf), '{')) { // a non-streaming JSON reply (some proxies)
                    $buf .= $body->getContents();

                    return $this->fromJson((array) json_decode($buf, true), $onText, $model);
                }
            }
            $buf = str_replace("\r\n", "\n", $buf);
            while (($pos = strpos($buf, "\n\n")) !== false) {
                $raw = substr($buf, 0, $pos);
                $buf = substr($buf, $pos + 2);
                $this->event($raw, $msg, $json, $onText);
            }
            if ($aborted && $aborted()) {
                $msg['stop_reason'] = 'aborted';
                break;
            }
        }
        if (trim($buf) !== '') {
            $this->event(trim($buf), $msg, $json, $onText);
        }
        ksort($msg['content']);
        $msg['content'] = array_values($msg['content']);

        return $msg;
    }

    private function event(string $raw, array &$msg, array &$json, callable $onText): void
    {
        $type = null;
        $data = '';
        foreach (explode("\n", $raw) as $line) {
            if (str_starts_with($line, 'event:')) {
                $type = trim(substr($line, 6));
            } elseif (str_starts_with($line, 'data:')) {
                $data .= ltrim(substr($line, 5));
            }
        }
        if ($data === '') {
            return;
        }
        $d = json_decode($data, true);
        if (! is_array($d)) {
            return;
        }
        $type ??= $d['type'] ?? null;

        switch ($type) {
            case 'message_start':
                $u = $d['message']['usage'] ?? [];
                $msg['model'] = (string) ($d['message']['model'] ?? $msg['model']);
                $msg['usage']['input'] += (int) ($u['input_tokens'] ?? 0);
                $msg['usage']['cache_read'] += (int) ($u['cache_read_input_tokens'] ?? 0);
                $msg['usage']['cache_write'] += (int) ($u['cache_creation_input_tokens'] ?? 0);
                $msg['usage']['output'] = max($msg['usage']['output'], (int) ($u['output_tokens'] ?? 0));
                break;
            case 'content_block_start':
                $i = (int) ($d['index'] ?? 0);
                $block = (array) ($d['content_block'] ?? []);
                if (($block['type'] ?? '') === 'tool_use') {
                    $block['input'] = [];
                    $json[$i] = '';
                } elseif (($block['type'] ?? '') === 'text') {
                    $block['text'] = (string) ($block['text'] ?? '');
                }
                $msg['content'][$i] = $block;
                break;
            case 'content_block_delta':
                $i = (int) ($d['index'] ?? 0);
                $delta = (array) ($d['delta'] ?? []);
                if (($delta['type'] ?? '') === 'text_delta') {
                    $text = (string) ($delta['text'] ?? '');
                    $msg['content'][$i]['text'] = ($msg['content'][$i]['text'] ?? '').$text;
                    $msg['content'][$i]['type'] ??= 'text';
                    if ($text !== '') {
                        $onText($text);
                    }
                } elseif (($delta['type'] ?? '') === 'input_json_delta') {
                    $json[$i] = ($json[$i] ?? '').($delta['partial_json'] ?? '');
                }
                break;
            case 'content_block_stop':
                $i = (int) ($d['index'] ?? 0);
                if (isset($json[$i])) {
                    $msg['content'][$i]['input'] = $json[$i] === '' ? [] : (array) (json_decode($json[$i], true) ?? []);
                    unset($json[$i]);
                }
                break;
            case 'message_delta':
                $msg['stop_reason'] = $d['delta']['stop_reason'] ?? $msg['stop_reason'];
                if (isset($d['usage']['output_tokens'])) {
                    $msg['usage']['output'] = (int) $d['usage']['output_tokens'];
                }
                break;
            case 'error':
                throw new AnthropicException((string) ($d['error']['message'] ?? 'Stream error'), 529, $d['error']['type'] ?? null);
        }
    }

    private function fromJson(array $d, callable $onText, string $model): array
    {
        if (($d['type'] ?? '') === 'error') {
            throw new AnthropicException((string) ($d['error']['message'] ?? 'Claude API error'), 500, $d['error']['type'] ?? null);
        }
        $content = array_values(array_map(fn ($b) => (array) $b, (array) ($d['content'] ?? [])));
        foreach ($content as $b) {
            if (($b['type'] ?? '') === 'text' && ($b['text'] ?? '') !== '') {
                $onText((string) $b['text']);
            }
        }
        $u = (array) ($d['usage'] ?? []);

        return [
            'content' => $content, 'stop_reason' => $d['stop_reason'] ?? null, 'model' => (string) ($d['model'] ?? $model),
            'usage' => [
                'input' => (int) ($u['input_tokens'] ?? 0), 'output' => (int) ($u['output_tokens'] ?? 0),
                'cache_read' => (int) ($u['cache_read_input_tokens'] ?? 0), 'cache_write' => (int) ($u['cache_creation_input_tokens'] ?? 0),
            ],
        ];
    }
}

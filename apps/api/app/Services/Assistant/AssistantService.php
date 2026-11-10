<?php

namespace App\Services\Assistant;

use App\Models\Assistant\AssistantConversation;
use App\Models\Assistant\AssistantMessage;
use App\Models\Product;
use App\Services\Catalog\CatalogQuery;
use Illuminate\Support\Facades\Log;

/**
 * One assistant turn: stores the shopper's message, runs the Claude tool-use loop (max 5 tool rounds),
 * streams text deltas and product/compare/order/cart cards through $emit, then stores the reply with
 * token usage and cost. Over budget or without an API key it degrades to plain catalog search results.
 */
class AssistantService
{
    public function __construct(
        private AnthropicClient $client,
        private AssistantTools $tools,
        private CatalogQuery $catalog,
    ) {}

    /** Conversation for this request: the given one when it belongs to the caller, else a new one. */
    public function conversation(AssistantContext $ctx, ?string $id): AssistantConversation
    {
        if ($id) {
            $c = $this->owned($ctx, $id);
            if ($c) {
                return $c;
            }
        }

        return AssistantConversation::query()->create([
            'user_id' => $ctx->user?->id, 'ip_hash' => $ctx->ipHash, 'client' => $ctx->client, 'locale' => $ctx->locale,
        ]);
    }

    /** Signed-in shoppers own their conversations; guest conversations are reachable by their (random UUID) id only. */
    public function owned(AssistantContext $ctx, string $id): ?AssistantConversation
    {
        $c = AssistantConversation::query()->find($id);
        if (! $c) {
            return null;
        }
        if ($c->user_id !== null) {
            return $ctx->user && $ctx->user->id === $c->user_id ? $c : null;
        }

        return $ctx->user ? null : $c;
    }

    /**
     * @param  callable(string, array<string, mixed>): void  $emit
     * @param  (callable(): bool)|null  $aborted
     * @return array{conversationId: string, messageId: int, text: string, events: list<array{type: string, data: array<string, mixed>}>, degraded: bool}
     */
    public function chat(AssistantContext $ctx, string $message, ?string $conversationId, callable $emit, ?callable $aborted = null): array
    {
        $conv = $this->conversation($ctx, $conversationId);
        $clean = Pii::scrub(trim($message));
        if (preg_match('/\p{Bengali}/u', $clean)) {
            $ctx->locale = 'bn';
        }
        $history = $this->history($conv);

        AssistantMessage::query()->create([
            'conversation_id' => $conv->id, 'user_id' => $ctx->user?->id, 'ip_hash' => $ctx->ipHash, 'role' => 'user', 'content' => $clean,
        ]);
        $conv->forceFill([
            'title' => $conv->title ?: mb_substr($clean, 0, 120), 'message_count' => $conv->message_count + 1, 'last_message_at' => now(),
        ])->save();

        $events = [];
        $push = function (string $type, array $data) use (&$events, $emit) {
            $events[] = ['type' => $type, 'data' => $data];
            $emit($type, $data);
        };

        $degraded = ! AssistantSettings::hasKey() || AssistantSettings::overBudget();
        $emit('meta', ['conversationId' => $conv->id, 'degraded' => $degraded]);

        $text = '';
        $usage = ['input' => 0, 'output' => 0, 'cache_read' => 0, 'cache_write' => 0, 'cost' => 0.0];
        $toolLog = [];
        $model = null;
        $error = null;
        $stopped = false;

        if ($degraded) {
            $text = $this->fallback($clean, $ctx, $emit, $push, ! AssistantSettings::hasKey() ? 'unavailable' : 'budget');
        } else {
            try {
                [$text, $model, $stopped] = $this->loop($ctx, $history, $clean, $emit, $push, $aborted, $usage, $toolLog);
            } catch (AnthropicException $e) {
                Log::warning('assistant.api_error', ['status' => $e->status, 'type' => $e->type]); // no message text: may echo input
                $error = 'api_'.($e->status ?: 'connection');
                $degraded = true;
                $text .= ($text === '' ? '' : "\n\n").$this->fallback($clean, $ctx, $emit, $push, 'error');
            }
        }

        $reply = AssistantMessage::query()->create([
            'conversation_id' => $conv->id, 'user_id' => $ctx->user?->id, 'ip_hash' => $ctx->ipHash, 'role' => 'assistant',
            'content' => Pii::scrub($text), 'cards' => $events, 'tools' => $toolLog ?: null, 'model' => $model,
            'input_tokens' => $usage['input'], 'output_tokens' => $usage['output'], 'cache_read_tokens' => $usage['cache_read'],
            'cache_write_tokens' => $usage['cache_write'], 'cost_usd' => $usage['cost'], 'degraded' => $degraded, 'stopped' => $stopped, 'error' => $error,
        ]);
        $conv->forceFill([
            'message_count' => $conv->message_count + 1, 'last_message_at' => now(), 'locale' => $ctx->locale,
            'input_tokens' => $conv->input_tokens + $usage['input'] + $usage['cache_read'] + $usage['cache_write'],
            'output_tokens' => $conv->output_tokens + $usage['output'], 'cost_usd' => round($conv->cost_usd + $usage['cost'], 6),
        ])->save();

        return ['conversationId' => $conv->id, 'messageId' => $reply->id, 'text' => $text, 'events' => $events, 'degraded' => $degraded];
    }

    /**
     * @return array{0: string, 1: string, 2: bool} text, model, stopped
     */
    private function loop(AssistantContext $ctx, array $messages, string $message, callable $emit, callable $push, ?callable $aborted, array &$usage, array &$toolLog): array
    {
        $messages[] = ['role' => 'user', 'content' => $message];
        $model = AssistantSettings::model();
        $max = max(0, (int) config('assistant.max_tool_rounds', 5));
        $system = SystemPrompt::blocks($ctx);
        $tools = $this->tools->definitions();
        $text = '';
        $needsBreak = false;
        $onText = function (string $delta) use (&$text, &$needsBreak, $emit) {
            if ($needsBreak && $text !== '') {
                $text .= "\n\n";
                $emit('text', ['delta' => "\n\n"]);
            }
            $needsBreak = false;
            $text .= $delta;
            $emit('text', ['delta' => $delta]);
        };

        for ($round = 0; $round <= $max; $round++) {
            $payload = [
                'model' => $model, 'max_tokens' => (int) config('assistant.max_tokens', 1024),
                'system' => $system, 'tools' => $tools, 'messages' => $messages,
            ];
            if ($round === $max) {
                $payload['tool_choice'] = ['type' => 'none']; // tool budget used up: answer with what we have
            }
            try {
                $res = $this->client->stream($payload, $onText, $aborted);
            } catch (AnthropicException $e) {
                $fallback = AssistantSettings::fallbackModel();
                if (! $e->retryable() || $text !== '' || $fallback === '' || $fallback === $model) {
                    throw $e;
                }
                $model = $payload['model'] = $fallback; // primary overloaded: retry once on the cheaper model
                $res = $this->client->stream($payload, $onText, $aborted);
            }
            $this->addUsage($usage, $res['model'] ?: $model, $res['usage']);
            if ($res['stop_reason'] === 'aborted') {
                return [$text, $model, true];
            }

            $calls = array_values(array_filter($res['content'], fn ($b) => ($b['type'] ?? '') === 'tool_use'));
            if ($res['stop_reason'] !== 'tool_use' || ! $calls) {
                break;
            }

            $messages[] = ['role' => 'assistant', 'content' => $this->assistantBlocks($res['content'])];
            $results = [];
            foreach ($calls as $call) {
                $name = (string) ($call['name'] ?? '');
                $input = is_array($call['input'] ?? null) ? $call['input'] : [];
                $emit('tool', ['name' => $name]);
                $out = $this->tools->run($name, $input, $ctx);
                foreach ($out['events'] as [$type, $data]) {
                    $push($type, $data);
                }
                $toolLog[] = ['name' => $name, 'input' => $this->logInput($input), 'ok' => ! isset($out['result']['error'])];
                $results[] = [
                    'type' => 'tool_result', 'tool_use_id' => (string) ($call['id'] ?? ''),
                    'content' => json_encode($out['result'], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR),
                    ...(isset($out['result']['error']) ? ['is_error' => true] : []),
                ];
            }
            $messages[] = ['role' => 'user', 'content' => $results];
            $needsBreak = true;
            if ($aborted && $aborted()) {
                return [$text, $model, true];
            }
        }

        return [$text, $model, false];
    }

    /** Previous turns as plain text (tool calls are not replayed; card ids are noted so "the first one" still resolves). */
    private function history(AssistantConversation $conv): array
    {
        $turns = (int) config('assistant.history_turns', 10) * 2;
        $rows = AssistantMessage::query()->where('conversation_id', $conv->id)->orderByDesc('id')->limit($turns)->get()->reverse()->values();
        $out = [];
        foreach ($rows as $m) {
            $content = (string) $m->content;
            if ($m->role === 'assistant') {
                $ids = collect($m->cards ?? [])->flatMap(fn ($e) => collect($e['data']['items'] ?? [])->pluck('id')
                    ->merge(isset($e['data']['product']['id']) ? [$e['data']['product']['id']] : []))->filter()->unique()->take(12)->all();
                if ($ids) {
                    $content .= "\n[Product ids shown: ".implode(', ', $ids).']';
                }
            }
            if (trim($content) === '') {
                continue;
            }
            $role = $m->role === 'assistant' ? 'assistant' : 'user';
            if ($out && end($out)['role'] === $role) { // keep strict user/assistant alternation
                $out[count($out) - 1]['content'] .= "\n\n".$content;
            } else {
                $out[] = ['role' => $role, 'content' => $content];
            }
        }
        while ($out && $out[0]['role'] !== 'user') {
            array_shift($out);
        }
        if ($out && end($out)['role'] === 'user') {
            array_pop($out); // an unanswered message (stopped/failed turn): drop it so the new one follows an assistant turn
        }

        return $out;
    }

    private function assistantBlocks(array $content): array
    {
        $out = [];
        foreach ($content as $b) {
            if (($b['type'] ?? '') === 'text' && trim((string) ($b['text'] ?? '')) !== '') {
                $out[] = ['type' => 'text', 'text' => (string) $b['text']];
            } elseif (($b['type'] ?? '') === 'tool_use') {
                $out[] = ['type' => 'tool_use', 'id' => (string) $b['id'], 'name' => (string) $b['name'], 'input' => (object) ($b['input'] ?? [])];
            }
        }

        return $out;
    }

    private function addUsage(array &$usage, string $model, array $u): void
    {
        foreach (['input', 'output', 'cache_read', 'cache_write'] as $k) {
            $usage[$k] += (int) ($u[$k] ?? 0);
        }
        $usage['cost'] = round($usage['cost'] + AssistantSettings::cost($model, (int) $u['input'], (int) $u['output'], (int) $u['cache_read'], (int) $u['cache_write']), 6);
    }

    private function logInput(array $input): array
    {
        return array_map(fn ($v) => is_string($v) ? mb_substr(Pii::scrub($v), 0, 120) : $v, $input);
    }

    // ----------------------------------------------------------- degraded mode

    /** Plain search results without the model: budget reached, no key, or API failure. */
    public function fallback(string $message, AssistantContext $ctx, callable $emit, callable $push, string $reason): string
    {
        $bn = $ctx->locale === 'bn';
        $found = $this->keywordSearch($message, $ctx->store);
        $lead = match ($reason) {
            'budget', 'unavailable' => $bn ? 'AI সহকারী এখন সীমিত মোডে আছে। ' : 'Skydrop AI is in basic mode right now. ',
            default => $bn ? 'দুঃখিত, AI সহকারী এই মুহূর্তে উত্তর দিতে পারছে না। ' : 'Sorry, Skydrop AI could not answer just now. ',
        };
        $text = $lead.($found
            ? ($bn ? 'আপনার কথার সাথে মিলে এমন কিছু পণ্য:' : 'Here are catalog matches for your message:')
            : ($bn ? 'মিল পাওয়া যায়নি। পণ্যের নাম লিখে সার্চ করে দেখুন।' : 'No matching products found. Try searching for a product name.'));
        $emit('text', ['delta' => $text]);
        if ($found) {
            $push('products', ['query' => $message, 'total' => count($found), 'items' => $found]);
        }

        return $text;
    }

    private const STOP = ['a', 'an', 'the', 'for', 'me', 'my', 'i', 'want', 'need', 'show', 'find', 'best', 'good', 'under', 'below', 'with', 'and', 'or', 'to', 'of', 'in', 'on', 'is', 'are', 'any', 'some', 'please', 'buy', 'cheap', 'price', 'tk', 'taka', 'bdt', 'which', 'what', 'can', 'you', 'do', 'have', 'recommend', 'suggest', 'help', 'choose', 'around', 'than', 'less', 'from'];

    /** @return list<array<string, mixed>> */
    private function keywordSearch(string $message, ?string $store): array
    {
        $words = array_values(array_filter(CatalogQuery::words(preg_replace('/[^\p{L}\p{N}\s-]/u', ' ', $message) ?? ''),
            fn ($w) => ! in_array($w, self::STOP, true) && ! is_numeric($w) && ! preg_match('/\p{Bengali}/u', $w)));
        $max = preg_match('/(\d[\d,]{2,})/', $message, $m) ? (int) str_replace(',', '', $m[1]) : null;
        $store = in_array($store, ['amazon', 'flipkart', 'bigbasket', 'myntra'], true) ? $store : 'all';
        $attempts = $words ? [implode(' ', $words)] : [];
        usort($words, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));
        foreach (array_slice($words, 0, 3) as $w) {
            $attempts[] = $w;
        }
        foreach (array_unique($attempts) as $q) {
            $items = $this->catalog->search(['q' => $q, 'store' => $store, 'max' => $max, 'perPage' => 8])['items'];
            if ($items->isNotEmpty()) {
                return $items->map(fn (Product $p) => $this->tools->card($p))->values()->all();
            }
        }

        return [];
    }
}

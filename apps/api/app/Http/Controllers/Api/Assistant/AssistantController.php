<?php

namespace App\Http\Controllers\Api\Assistant;

use App\Http\Controllers\Controller;
use App\Models\Assistant\AssistantFeedback;
use App\Models\Assistant\AssistantMessage;
use App\Services\Assistant\AssistantContext;
use App\Services\Assistant\AssistantLimits;
use App\Services\Assistant\AssistantService;
use App\Services\Assistant\AssistantSettings;
use App\Services\Assistant\Pii;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/** AI shopping assistant (docs/API.md "AI assistant"). All routes sit behind the "ai_assistant" feature switch. */
class AssistantController extends Controller
{
    public function __construct(private AssistantService $assistant, private AssistantLimits $limits) {}

    /** GET /assistant/config */
    public function config(Request $request): JsonResponse
    {
        $ctx = $this->context($request);

        return response()->json([
            'enabled' => AssistantSettings::enabled(),
            'basic' => ! AssistantSettings::hasKey() || AssistantSettings::overBudget(),
            'greeting' => ['en' => (string) AssistantSettings::get('greeting.en'), 'bn' => (string) AssistantSettings::get('greeting.bn')],
            'prompts' => ['en' => AssistantSettings::prompts('en'), 'bn' => AssistantSettings::prompts('bn')],
            'limit' => $this->limits->limit($ctx),
            'remaining' => $this->limits->remaining($ctx),
            'signedIn' => ! $ctx->guest(),
        ]);
    }

    /** POST /assistant/chat — Server-Sent Events, or JSON with ?stream=0. */
    public function chat(Request $request): JsonResponse|StreamedResponse
    {
        $data = $request->validate([
            'conversationId' => ['nullable', 'uuid'],
            'message' => ['required', 'string', 'max:2000'],
            'store' => ['nullable', 'in:all,amazon,flipkart,bigbasket,myntra'],
            'locale' => ['nullable', 'in:en,bn'],
            'client' => ['nullable', 'in:web,mobile'],
            'context' => ['nullable', 'array'],
            'context.productId' => ['nullable', 'string', 'max:64'],
            'context.cartIds' => ['nullable', 'array', 'max:50'],
            'context.cartIds.*' => ['string', 'max:64'],
        ]);
        $ctx = $this->context($request, $data);

        $limit = $this->limits->limit($ctx);
        if ($this->limits->used($ctx) >= $limit) {
            return response()->json([
                'message' => $ctx->guest() ? 'Daily AI message limit reached. Sign in for more messages.' : 'Daily AI message limit reached. Try again tomorrow.',
                'code' => 'rate_limited', 'limit' => $limit, 'remaining' => 0,
                'retryAfter' => (int) now()->diffInSeconds(now()->addDay()->startOfDay(), true),
            ], 429);
        }

        $message = (string) $data['message'];
        $conversationId = $data['conversationId'] ?? null;

        if (! $request->boolean('stream', true)) {
            $res = $this->assistant->chat($ctx, $message, $conversationId, fn () => null);

            return response()->json($res + ['remaining' => $this->limits->remaining($ctx)]);
        }

        return response()->stream(function () use ($ctx, $message, $conversationId) {
            ignore_user_abort(true);
            $send = function (string $event, array $payload) {
                echo 'event: '.$event."\n".'data: '.json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PARTIAL_OUTPUT_ON_ERROR)."\n\n";
                if (! app()->runningUnitTests() && ob_get_level() > 0) {
                    @ob_flush();
                }
                flush();
            };
            try {
                $res = $this->assistant->chat($ctx, $message, $conversationId, $send, fn () => connection_aborted() === 1);
                $send('done', ['conversationId' => $res['conversationId'], 'messageId' => $res['messageId'], 'degraded' => $res['degraded'], 'remaining' => $this->limits->remaining($ctx)]);
            } catch (Throwable $e) {
                report($e);
                $send('error', ['code' => 'server_error', 'message' => 'Something went wrong. Please try again.']);
            }
        }, 200, [
            'Content-Type' => 'text/event-stream; charset=utf-8',
            'Cache-Control' => 'no-cache, no-transform',
            'X-Accel-Buffering' => 'no',
        ]);
    }

    /** GET /assistant/conversations/{id} */
    public function show(Request $request, string $id): JsonResponse
    {
        $c = $this->assistant->owned($this->context($request), $id);
        abort_unless($c, 404);

        return response()->json(['id' => $c->id, 'messages' => $c->messages->map->toApi()->values()->all()]);
    }

    /** DELETE /assistant/conversations/{id} — "Clear conversation". Messages are kept for usage accounting but detached from the shopper. */
    public function destroy(Request $request, string $id): JsonResponse
    {
        $c = $this->assistant->owned($this->context($request), $id);
        abort_unless($c, 404);
        $c->forceFill(['title' => '[cleared by shopper]'])->save();
        $c->messages()->update(['content' => '[cleared]', 'cards' => null]);

        return response()->json(['ok' => true]);
    }

    /** POST /assistant/feedback { messageId, rating: "up"|"down", comment? } */
    public function feedback(Request $request): JsonResponse
    {
        $data = $request->validate([
            'messageId' => ['required', 'integer'],
            'rating' => ['required', 'in:up,down,1,-1'],
            'comment' => ['nullable', 'string', 'max:500'],
        ]);
        $ctx = $this->context($request);
        $msg = AssistantMessage::query()->where('role', 'assistant')->find($data['messageId']);
        $conv = $msg ? $this->assistant->owned($ctx, $msg->conversation_id) : null;
        abort_unless($msg && $conv, 404);

        AssistantFeedback::query()->updateOrCreate(
            ['message_id' => $msg->id, 'user_id' => $ctx->user?->id, 'ip_hash' => $ctx->user ? null : $ctx->ipHash],
            ['conversation_id' => $conv->id, 'rating' => in_array($data['rating'], ['up', '1'], true) ? 1 : -1,
                'comment' => isset($data['comment']) ? mb_substr(Pii::scrub($data['comment']), 0, 500) : null],
        );

        return response()->json(['ok' => true]);
    }

    private function context(Request $request, array $data = []): AssistantContext
    {
        $ip = (string) $request->ip();
        $key = (string) config('assistant.internal_key');
        // The Next.js proxy forwards the shopper's IP; trust it only with the shared internal key.
        if ($key !== '' && hash_equals($key, (string) $request->header('X-Internal-Key')) && filter_var($request->header('X-Client-IP'), FILTER_VALIDATE_IP)) {
            $ip = (string) $request->header('X-Client-IP');
        }
        $ctxIn = (array) ($data['context'] ?? []);

        return new AssistantContext(
            user: $request->user('sanctum'),
            ipHash: hash_hmac('sha256', $ip, (string) config('app.key')),
            store: $data['store'] ?? null,
            productId: $ctxIn['productId'] ?? null,
            cartIds: array_values(array_filter((array) ($ctxIn['cartIds'] ?? []), 'is_string')),
            locale: $data['locale'] ?? 'en',
            client: $data['client'] ?? 'web',
        );
    }
}

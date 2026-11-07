<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Payments\PaymentService;
use App\Services\Commerce\Skydrop\SkydropClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Log;

/**
 * POST webhooks/skydrop. Body JSON: { event|status, tracking_code|trackingCode, reference?, consignment_id?, at?, note?, cod_collected? }
 * Header X-Skydrop-Signature: hex HMAC-SHA256 of the raw body with SKYDROP_WEBHOOK_SECRET (optional "sha256=" prefix).
 */
class SkydropWebhookController extends Controller
{
    public function __invoke(Request $request, OrderWorkflow $workflow, PaymentService $payments): JsonResponse
    {
        $raw = $request->getContent();
        if (! SkydropClient::validSignature($raw, $request->header('X-Skydrop-Signature'))) {
            return response()->json(['message' => 'Invalid signature.'], 401);
        }
        $body = json_decode($raw, true);
        if (! is_array($body)) {
            return response()->json(['message' => 'Invalid JSON.'], 400);
        }
        $data = $body['data'] ?? $body;
        $event = (string) ($body['event'] ?? $data['event'] ?? $data['status'] ?? '');
        $code = $data['tracking_code'] ?? $data['trackingCode'] ?? null;
        $ref = $data['reference'] ?? $data['order_id'] ?? null;

        $order = Order::query()
            ->when($code, fn ($q) => $q->where('tracking_code', $code), fn ($q) => $q->whereKey((string) $ref))
            ->first();
        if (! $order) {
            return response()->json(['message' => 'Unknown consignment.'], 404);
        }
        if (! empty($data['consignment_id']) && ! $order->skydrop_consignment_id) {
            $order->forceFill(['skydrop_consignment_id' => (string) $data['consignment_id']])->save();
        }

        // Fulfilment module: keep every scan, rider lat/lng and proof of delivery for the tracking map.
        rescue(fn () => app(\App\Services\Fulfilment\TrackingRecorder::class)->record($order, $event, $data), null, true);

        $status = SkydropClient::mapEvent($event);
        if (! $status) {
            return response()->json(['ok' => true, 'ignored' => $event]);
        }
        if (! empty($data['cod_collected']) && $status === 'delivered') {
            $payments->recordCollection($order, (float) $data['cod_collected'], (string) ($data['consignment_id'] ?? $order->tracking_code));
            $order->refresh();
        }
        if ($order->status === $status || ! $workflow->canTransition($order, $status)) {
            return response()->json(['ok' => true, 'status' => $order->status, 'duplicate' => true]);
        }
        try {
            $at = isset($data['at']) ? Carbon::parse($data['at']) : null;
            $workflow->transition($order, $status, 'skydrop', $data['note'] ?? null, $at);
        } catch (CommerceException $e) {
            Log::warning('[skydrop] '.$e->getMessage(), ['order' => $order->id, 'event' => $event]);

            return response()->json(['ok' => false, 'message' => $e->getMessage()], 409);
        }

        return response()->json(['ok' => true, 'status' => $order->refresh()->status]);
    }
}

<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Payment;
use App\Services\Commerce\Payments\InvalidCallback;
use App\Services\Commerce\Payments\MockGateway;
use App\Services\Commerce\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    public function __construct(private PaymentService $payments) {}

    /** Mock checkout page (PAYMENTS_MODE=mock). Signed URL. */
    public function mockShow(Request $request, Payment $payment): Response
    {
        abort_unless(config('retail.payments.mode') === 'mock' && PaymentService::mockAllowed(), 404);
        $order = Order::query()->findOrFail($payment->order_id);
        $action = url('/api/v1/webhooks/payments/'.$payment->gateway).'?payment='.$payment->id.'&return=1';

        return response()->view('commerce.mock-payment', [
            'payment' => $payment, 'order' => $order, 'action' => $action,
            'sigSuccess' => MockGateway::signature($payment->id, 'success'),
            'sigFail' => MockGateway::signature($payment->id, 'failed'),
            'sigCancel' => MockGateway::signature($payment->id, 'cancelled'),
        ]);
    }

    /**
     * POST|GET webhooks/payments/{gateway}: browser return (return=1 -> redirect to the storefront) or
     * server-to-server IPN (JSON). Always validated with the gateway; idempotent.
     */
    public function callback(Request $request, string $gateway): JsonResponse|RedirectResponse
    {
        $gateway = $gateway === 'card' ? 'sslcommerz' : $gateway;
        $payment = $this->payments->locate($gateway, $request);
        if (! $payment) {
            return response()->json(['message' => 'Unknown payment.'], 404);
        }

        try {
            $payment = $this->payments->handleCallback($payment, $request);
        } catch (InvalidCallback $e) {
            Log::warning("[payments:{$gateway}] invalid callback: ".$e->getMessage(), ['payment' => $payment->id]);

            return response()->json(['message' => 'Invalid callback.'], 400);
        } catch (\Throwable $e) {
            Log::error("[payments:{$gateway}] callback error: ".$e->getMessage(), ['payment' => $payment->id]);
            if (! $this->isBrowser($request, $gateway)) {
                return response()->json(['message' => 'Could not confirm the payment yet.'], 502);
            }
        }

        $order = Order::query()->find($payment->order_id);
        if ($this->isBrowser($request, $gateway)) {
            return redirect()->away($this->returnUrl($order, $payment, $request));
        }

        return response()->json(['ok' => true, 'status' => $payment->status, 'orderId' => $order?->id, 'paymentStatus' => $order?->payment_status]);
    }

    private function isBrowser(Request $request, string $gateway): bool
    {
        // bKash and Nagad only return the shopper's browser (GET); SSLCommerz/mock add return=1 to browser URLs.
        return $request->boolean('return') || $request->isMethod('get');
    }

    /**
     * Shopper's return URL (order.return_url or {web}/checkout/success) + order=SR-XXXXXX&status=paid|failed|cancelled.
     * Works for web URLs and app deep links (skydropretail://...).
     */
    public static function returnUrl(?Order $order, Payment $payment, ?Request $request = null): string
    {
        $base = $order?->return_url ?: rtrim((string) config('retail.web_url'), '/').'/checkout/success';
        $cancelled = in_array(strtolower((string) $request?->input('status')), ['cancel', 'cancelled'], true);
        $status = $payment->status === 'paid' ? 'paid' : ($cancelled ? 'cancelled' : 'failed');
        $sep = str_contains($base, '?') ? '&' : '?';

        return $base.$sep.http_build_query(['order' => $order?->id, 'status' => $status]);
    }
}

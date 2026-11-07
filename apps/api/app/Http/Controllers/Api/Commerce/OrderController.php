<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Commerce\AddressService;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()->where('user_id', $request->user()->id)->with(['items', 'events'])->latest()->limit(200)->get();

        return response()->json(['items' => $orders->map->toApi()->values()]);
    }

    public function show(Request $request, string $id): JsonResponse
    {
        return response()->json(['order' => $this->own($request, $id)->toApi()]);
    }

    public function store(Request $request, OrderService $orders): JsonResponse
    {
        $addressRules = [];
        foreach (AddressService::rules() as $k => $r) {
            $addressRules['address.'.$k] = array_map(fn ($x) => $x === 'required' ? 'required_without:addressId' : $x, $r);
        }
        $data = $request->validate(CheckoutController::itemRules() + [
            'items.*.unitBdt' => ['required', 'numeric', 'min:1'],
            'addressId' => ['nullable', 'integer', 'required_without:address'],
            'address' => ['nullable', 'array', 'required_without:addressId'],
            'payment' => ['required', Rule::in(['bkash', 'nagad', 'card', 'advance50', 'half'])],
            'paymentVia' => ['nullable', Rule::in(PaymentService::WALLETS)],
            'coupon' => ['nullable', 'string', 'max:30'],
            'redeemPoints' => ['nullable', 'integer', 'min:0'],
            'returnUrl' => ['nullable', 'url', 'max:500', function ($attr, $value, $fail) {
                if (! self::allowedReturnUrl($value)) {
                    $fail('returnUrl must point to the storefront.');
                }
            }],
        ] + $addressRules);

        $r = $orders->place($request->user(), $data);

        return response()->json(['order' => $r['order']->toApi(), 'payment' => $r['payment']], 201);
    }

    /** Retry payment for an unpaid order. */
    public function pay(Request $request, string $id, PaymentService $payments): JsonResponse
    {
        $order = $this->own($request, $id);
        if ($order->status === 'cancelled' || in_array($order->payment_status, ['paid', 'partial', 'refunded', 'refund_pending'], true)) {
            throw new CommerceException('This order does not need an online payment.', 409);
        }

        return response()->json(['order' => $order->toApi(), 'payment' => $payments->start($order)]);
    }

    public function cancel(Request $request, string $id, OrderWorkflow $workflow): JsonResponse
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200']]);
        $order = $workflow->cancel($this->own($request, $id), 'customer', $data['reason'] ?? null);

        return response()->json(['order' => $order->toApi()]);
    }

    public function invoice(Request $request, string $id): Response
    {
        $order = $this->own($request, $id)->load(['items', 'user', 'payments']);

        return response()->view('commerce.invoice', ['order' => $order])->header('Content-Type', 'text/html; charset=utf-8');
    }

    public static function allowedReturnUrl(?string $url): bool
    {
        if (! $url) {
            return true;
        }
        $web = parse_url((string) config('retail.web_url'));
        $u = parse_url($url);

        return isset($u['host'], $web['host']) && strcasecmp($u['host'], $web['host']) === 0
            && ($u['scheme'] ?? '') === ($web['scheme'] ?? 'https') && ($u['port'] ?? null) === ($web['port'] ?? null);
    }

    private function own(Request $request, string $id): Order
    {
        $order = Order::query()->with(['items', 'events'])->find(strtoupper($id));
        if (! $order || $order->user_id !== $request->user()->id) {
            abort(404, 'Order not found.');
        }

        return $order;
    }
}

<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Services\Commerce\AddressService;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\OrderService;
use App\Services\Commerce\OrderWorkflow;
use App\Services\Commerce\Payments\PaymentService;
use App\Services\Tracking\Attribution;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

class OrderController extends Controller
{
    /** Custom URL schemes of the Android/iOS apps accepted as returnUrl. */
    public const APP_SCHEMES = ['skydropretail'];

    public function index(Request $request): JsonResponse
    {
        $orders = Order::query()->where('user_id', $request->user()->id)->where('kind', 'goods') // money: gift card / Plus orders are not listed
            ->with(['items', 'events'])->latest()->limit(200)->get();

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
            'payment' => ['required', Rule::in(['bkash', 'nagad', 'card', 'advance50', 'half', 'card_emi'])], // card_emi: money module
            'paymentVia' => ['nullable', Rule::in(PaymentService::WALLETS)],
            'coupon' => ['nullable', 'string', 'max:30'],
            'redeemPoints' => ['nullable', 'integer', 'min:0'],
            'useWallet' => ['nullable'], 'emiTenure' => ['nullable', 'integer', 'min:1', 'max:36'], 'emiBank' => ['nullable', 'string', 'max:30'], // money module
            'ref' => ['nullable', 'string', 'max:20'], // affiliate code (growth module, last click)
            'unboxing' => ['nullable', 'boolean'], // fulfilment: paid unboxing at the Kolkata hub
            'returnUrl' => ['nullable', 'string', 'max:500', function ($attr, $value, $fail) {
                if (! self::allowedReturnUrl($value)) {
                    $fail('returnUrl must point to the storefront.');
                }
            }],
        ] + $addressRules + Attribution::rules());
        $data['attribution'] = Attribution::fromRequest($request, $request->input('attribution'));

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
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:200'], 'refundTo' => ['nullable', Rule::in(['wallet', 'original'])]]);
        \App\Services\Money\WalletPayments::prefer(strtoupper($id), $data['refundTo'] ?? null); // money: refund destination
        $order = $workflow->cancel($this->own($request, $id), 'customer', $data['reason'] ?? null);

        return response()->json(['order' => $order->toApi()]);
    }

    public function invoice(Request $request, string $id): Response
    {
        $order = $this->own($request, $id)->load(['items', 'user', 'payments']);

        return response()->view('commerce.invoice', ['order' => $order])->header('Content-Type', 'text/html; charset=utf-8');
    }

    /** Signed link (invoiceUrl in Order JSON) so a browser tab can open the invoice without a bearer token. */
    public function signedInvoice(string $id): Response
    {
        $order = Order::query()->with(['items', 'user', 'payments'])->findOrFail(strtoupper($id));

        return response()->view('commerce.invoice', ['order' => $order])->header('Content-Type', 'text/html; charset=utf-8');
    }

    public static function allowedReturnUrl(?string $url): bool
    {
        if (! $url) {
            return true;
        }
        $u = parse_url($url);
        if ($u === false || empty($u['scheme'])) {
            return false;
        }
        // Mobile app deep links (skydropretail://checkout/success).
        if (in_array(strtolower($u['scheme']), self::APP_SCHEMES, true)) {
            return true;
        }
        $web = parse_url((string) config('retail.web_url'));

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

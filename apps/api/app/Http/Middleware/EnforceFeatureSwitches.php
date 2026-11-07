<?php

namespace App\Http\Middleware;

use App\Models\User;
use App\Services\Commerce\Phone;
use App\Support\Features;
use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Platform-level feature switches for the whole API (appended to the "api" middleware group):
 *
 *   mobile_app off   → requests with "X-Client: mobile" get 503
 *   checkout off     → POST orders, checkout/quote, checkout/verify get 503
 *   signups off      → POST auth/otp/verify for a phone with no account gets 403
 *   pay.{method} off → POST orders with that payment method gets 422
 *   coupons / points off → "coupon" / "redeemPoints" are dropped from quote and order requests; the JSON response
 *                          gets a "notices" list explaining why
 *   paste_link off   → POST products/resolve 404;  offers off → GET offers 404;  reviews off → review endpoints 404
 */
class EnforceFeatureSwitches
{
    public const PAYMENT_KEYS = ['bkash' => 'pay.bkash', 'nagad' => 'pay.nagad', 'card' => 'pay.card', 'advance50' => 'pay.advance50', 'half' => 'pay.advance50', 'emi' => 'pay.emi', 'card_emi' => 'pay.emi'];

    public function handle(Request $request, Closure $next): Response
    {
        $path = trim(preg_replace('#^api/v1#', '', $request->path()), '/');
        $post = $request->isMethod('POST');

        if (strtolower((string) $request->header('X-Client')) === 'mobile' && ! Features::enabled('mobile_app') && $path !== 'features') {
            return $this->unavailable('The app is temporarily unavailable. Please use the website for now.', 'mobile_app', 503);
        }

        $checkoutPath = $post && in_array($path, ['orders', 'checkout/quote', 'checkout/verify'], true);
        if ($checkoutPath && ! Features::enabled('checkout')) {
            return $this->unavailable('Checkout is paused right now. You can keep browsing and adding to your cart; please try again a little later.', 'checkout', 503);
        }

        if ($post && $path === 'auth/otp/verify' && ! Features::enabled('signups') && ! $this->accountExists($request->input('phone'))) {
            return $this->unavailable('New sign-ups are paused right now. Existing customers can still sign in.', 'signups', 403);
        }

        foreach (['products/resolve' => 'paste_link', 'offers' => 'offers'] as $p => $feature) {
            if ($path === $p && ! Features::enabled($feature)) {
                return $this->unavailable('This feature is currently unavailable.', $feature, 404);
            }
        }
        if (preg_match('#^(products/[^/]+/reviews|reviews(/.*)?|me/reviews)$#', $path) && ! Features::enabled('reviews')) {
            return $this->unavailable('This feature is currently unavailable.', 'reviews', 404);
        }

        $notices = [];
        if ($post && in_array($path, ['orders', 'checkout/quote'], true)) {
            if ($path === 'orders') {
                $method = (string) $request->input('payment');
                $keys = array_filter([self::PAYMENT_KEYS[$method] ?? null, in_array($method, ['advance50', 'half'], true) ? (self::PAYMENT_KEYS[(string) $request->input('paymentVia', 'bkash')] ?? null) : null]);
                foreach ($keys as $key) {
                    if (array_key_exists($key, Features::REGISTRY) && ! Features::enabled($key)) {
                        return response()->json([
                            'message' => 'This payment method is not available right now. Please choose another one.',
                            'error' => 'payment_method_disabled', 'feature' => $key,
                            'errors' => ['payment' => ['This payment method is not available right now.']],
                        ], 422);
                    }
                }
            }
            if (filled($request->input('coupon')) && ! Features::enabled('coupons')) {
                $request->request->remove('coupon');
                $request->merge(['coupon' => null]);
                $notices[] = ['feature' => 'coupons', 'message' => 'Coupons are not available right now, so the code was not applied.'];
            }
            if ((int) $request->input('redeemPoints') > 0 && ! Features::enabled('points')) {
                $request->merge(['redeemPoints' => 0]);
                $notices[] = ['feature' => 'points', 'message' => 'Skydrop Points cannot be redeemed right now.'];
            }
        }

        $response = $next($request);

        if ($notices && $response instanceof JsonResponse && is_array($data = $response->getData(true))) {
            $data['notices'] = [...($data['notices'] ?? []), ...$notices];
            $response->setData($data);
        }

        return $response;
    }

    private function accountExists(mixed $phone): bool
    {
        try {
            $normalized = class_exists(Phone::class) ? Phone::normalize((string) $phone) : (string) $phone;

            return $normalized !== null && User::query()->where('phone', $normalized)->exists();
        } catch (Throwable) {
            return true; // fail open: never lock out existing customers on a lookup error
        }
    }

    private function unavailable(string $message, string $feature, int $status): JsonResponse
    {
        return response()->json(['message' => $message, 'error' => 'feature_disabled', 'feature' => $feature], $status);
    }
}

<?php

namespace App\Http\Controllers\Api\Money;

use App\Http\Controllers\Api\Commerce\OrderController;
use App\Http\Controllers\Controller;
use App\Models\GiftCard;
use App\Services\Commerce\Payments\PaymentService;
use App\Services\Money\EmiService;
use App\Services\Money\GiftCardService;
use App\Services\Money\MembershipService;
use App\Services\Money\WalletPayments;
use App\Services\Money\WalletService;
use App\Support\Features;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Wallet, gift cards, Skydrop Plus and EMI endpoints (routes/api_wallet.php, docs/API.md). */
class MoneyController extends Controller
{
    // ---- Wallet ----

    public function wallet(Request $request, WalletService $wallet): JsonResponse
    {
        return response()->json($wallet->summary($request->user()) + [
            'refund' => ['default' => WalletPayments::defaultDestination(), 'bonusPct' => WalletPayments::bonusPct()],
        ]);
    }

    // ---- Gift cards ----

    public function giftCardConfig(GiftCardService $cards): JsonResponse
    {
        return response()->json($cards->config());
    }

    public function giftCards(Request $request): JsonResponse
    {
        $items = GiftCard::query()->where('purchaser_user_id', $request->user()->id)->where('status', '!=', 'pending')->latest('id')->limit(100)->get();

        return response()->json(['items' => $items->map->toApi(true)->values()]);
    }

    public function buyGiftCard(Request $request, GiftCardService $cards): JsonResponse
    {
        $data = $request->validate([
            'amountBdt' => ['required', 'integer', 'min:1'],
            'design' => ['nullable', 'string', 'max:30'],
            'recipientName' => ['required', 'string', 'max:100'],
            'recipientPhone' => ['nullable', 'string', 'max:20', 'required_without:recipientEmail'],
            'recipientEmail' => ['nullable', 'email', 'max:190'],
            'message' => ['nullable', 'string', 'max:300'],
            'payment' => ['required', Rule::in(PaymentService::WALLETS)],
            'returnUrl' => ['nullable', 'string', 'max:500', $this->returnRule()],
        ]);
        $data['returnUrl'] ??= rtrim((string) config('retail.web_url'), '/').'/gift-cards/success';
        $r = $cards->purchase($request->user(), $data);

        return response()->json(['giftCard' => $r['giftCard']->toApi(), 'order' => ['id' => $r['order']->id, 'total' => (int) $r['order']->total_bdt],
            'payment' => $r['payment']], 201);
    }

    public function redeemGiftCard(Request $request, GiftCardService $cards): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:40']]);

        return response()->json($cards->redeem($request->user(), $data['code']));
    }

    // ---- Skydrop Plus ----

    public function plans(MembershipService $plus): JsonResponse
    {
        return response()->json(['items' => $plus->plans()->map->toApi()->values()]);
    }

    public function membership(Request $request, MembershipService $plus): JsonResponse
    {
        $user = $request->user();
        $m = $plus->current($user);

        return response()->json([
            'membership' => $plus->membershipApi($m),
            'benefits' => $m?->plan ? MembershipService::benefitsApi(MembershipService::normalize($m->plan->benefits ?? [])) : null,
            'savings' => $plus->savings($user),
        ]);
    }

    public function buyMembership(Request $request, MembershipService $plus): JsonResponse
    {
        $data = $request->validate([
            'plan' => ['required'],
            'payment' => ['required', Rule::in(PaymentService::WALLETS)],
            'returnUrl' => ['nullable', 'string', 'max:500', $this->returnRule()],
        ]);
        $plan = $plus->plan($data['plan']);
        $r = app(\App\Services\Money\DigitalOrders::class)->create($request->user(), 'membership', (float) $plan->price_bdt, $data['payment'],
            ['plan_id' => $plan->id], $data['returnUrl'] ?? rtrim((string) config('retail.web_url'), '/').'/plus/success');

        return response()->json(['order' => ['id' => $r['order']->id, 'total' => (int) $r['order']->total_bdt], 'plan' => $plan->toApi(), 'payment' => $r['payment']], 201);
    }

    // ---- EMI ----

    /** GET /emi?amount= → config plus, for an amount, the cheapest plan per tenure and "from ৳X/month". */
    public function emi(Request $request, EmiService $emi): JsonResponse
    {
        $amount = (float) $request->query('amount', 0);
        $out = $emi->config();
        if ($amount > 0) {
            $out['amountBdt'] = (int) round($amount);
            $out['eligible'] = $emi->eligible($amount);
            $out['fromMonthlyBdt'] = $emi->fromMonthly($amount);
            $out['options'] = $out['eligible'] ? array_map(fn ($t) => $emi->plan($amount, $t), $emi->tenures()) : [];
        }

        return response()->json($out);
    }

    /** Which money features are on (for clients that do not read /features). */
    public function status(): JsonResponse
    {
        return response()->json(['wallet' => Features::enabled('wallet'), 'giftCards' => Features::enabled('gift_cards'),
            'membership' => Features::enabled('membership'), 'emi' => app(EmiService::class)->enabled()]);
    }

    private function returnRule(): \Closure
    {
        return function ($attr, $value, $fail) {
            if (! OrderController::allowedReturnUrl($value)) {
                $fail('returnUrl must point to the storefront.');
            }
        };
    }
}

<?php

namespace App\Http\Controllers\Api\Growth;

use App\Http\Controllers\Controller;
use App\Models\Affiliate;
use App\Services\Growth\AffiliateService;
use App\Services\Growth\GrowthSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class AffiliateController extends Controller
{
    public function __construct(private AffiliateService $affiliates) {}

    /** Public program rules for the /affiliate landing page. */
    public function program(): JsonResponse
    {
        return response()->json(['program' => GrowthSettings::program()]);
    }

    public function apply(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:16'],
            'payoutMethod' => ['nullable', Rule::in(array_keys(Affiliate::PAYOUT_METHODS))],
            'payoutNumber' => ['nullable', 'string', 'max:25'],
            'about' => ['nullable', 'string', 'max:1000'],
        ]);
        $existed = Affiliate::query()->where('user_id', $request->user()->id)->exists();
        $this->affiliates->apply($request->user(), $data);

        return response()->json($this->affiliates->dashboard($request->user()), $existed ? 200 : 201);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json($this->affiliates->dashboard($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $a = Affiliate::query()->where('user_id', $request->user()->id)->firstOrFail();
        $data = $request->validate([
            'code' => ['nullable', 'string', 'max:16'],
            'payoutMethod' => ['nullable', Rule::in(array_keys(Affiliate::PAYOUT_METHODS))],
            'payoutNumber' => ['nullable', 'string', 'max:25'],
        ]);
        $this->affiliates->update($a, $data);

        return response()->json($this->affiliates->dashboard($request->user()));
    }

    public function requestPayout(Request $request): JsonResponse
    {
        $a = Affiliate::query()->where('user_id', $request->user()->id)->firstOrFail();
        $payout = $this->affiliates->requestPayout($a);

        return response()->json(['payout' => $payout->toApi()] + $this->affiliates->dashboard($request->user()), 201);
    }

    /** POST /r/click { code, productId?, url? } (public). */
    public function click(Request $request): JsonResponse
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20'],
            'productId' => ['nullable', 'string', 'max:40'],
            'url' => ['nullable', 'string', 'max:500'],
        ]);
        $ok = $this->affiliates->recordClick($data['code'], $data['productId'] ?? null, $data['url'] ?? null, $request->ip(), $request->userAgent());

        return response()->json(['ok' => true, 'valid' => $ok, 'cookieDays' => GrowthSettings::int('affiliate.cookie_days')]);
    }
}

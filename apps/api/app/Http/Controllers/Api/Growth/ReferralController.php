<?php

namespace App\Http\Controllers\Api\Growth;

use App\Http\Controllers\Controller;
use App\Services\Growth\ReferralService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReferralController extends Controller
{
    public function me(Request $request, ReferralService $referrals): JsonResponse
    {
        return response()->json($referrals->summary($request->user()));
    }
}

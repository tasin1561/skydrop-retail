<?php

namespace App\Http\Controllers\Api\Shopping;

use App\Http\Controllers\Api\Commerce\CheckoutController;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Shopping\CompareService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class CompareController extends Controller
{
    /** GET products/{id}/compare */
    public function show(string $id, CompareService $compare): JsonResponse
    {
        return response()->json($compare->compare(Product::query()->findOrFail($id)));
    }

    /** POST checkout/alternatives { items } → cheaper identical listings for cart lines. */
    public function alternatives(Request $request, CompareService $compare): JsonResponse
    {
        $data = $request->validate(CheckoutController::itemRules());

        return response()->json(['items' => $compare->cheaperFor($data['items'])]);
    }
}

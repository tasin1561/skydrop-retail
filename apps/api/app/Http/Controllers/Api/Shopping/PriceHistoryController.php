<?php

namespace App\Http\Controllers\Api\Shopping;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Shopping\PriceHistoryService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PriceHistoryController extends Controller
{
    /** GET products/{id}/price-history?days=30|90|365 */
    public function show(Request $request, string $id, PriceHistoryService $history): JsonResponse
    {
        $p = Product::query()->findOrFail($id);

        return response()->json($history->series($p, (int) $request->query('days', 30)))
            ->header('Cache-Control', 'public, max-age=300');
    }
}

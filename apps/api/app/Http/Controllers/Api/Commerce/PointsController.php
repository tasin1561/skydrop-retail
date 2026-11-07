<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Services\Commerce\PointsService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PointsController extends Controller
{
    public function show(Request $request, PointsService $points): JsonResponse
    {
        return response()->json($points->summary($request->user()));
    }
}

<?php

namespace App\Http\Controllers\Api\System;

use App\Http\Controllers\Controller;
use App\Services\System\HealthService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Health endpoints for load balancers and uptime monitors (UptimeRobot, Better Stack). See docs/OPERATIONS.md. */
class HealthController extends Controller
{
    /** GET /health/ready: DB + cache + queue worker heartbeat. 200 when all pass, else 503. */
    public function ready(HealthService $health): JsonResponse
    {
        $r = $health->ready();

        return response()->json($r, $r['ok'] ? 200 : 503)->header('Cache-Control', 'no-store');
    }

    /** GET /health/details with X-Health-Token: the full System → Health report. 404 when HEALTH_TOKEN is unset. */
    public function details(Request $request, HealthService $health): JsonResponse
    {
        $token = (string) config('system.health_token');
        abort_if($token === '', 404);
        $given = (string) ($request->header('X-Health-Token') ?: $request->bearerToken());
        if (! hash_equals($token, $given)) {
            return response()->json(['message' => 'Invalid health token.', 'code' => 'unauthorized'], 401);
        }
        $r = $health->report();

        return response()->json($r, $r['status'] === 'red' ? 503 : 200)->header('Cache-Control', 'no-store');
    }
}

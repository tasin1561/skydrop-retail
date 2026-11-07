<?php

namespace App\Http\Middleware;

use App\Support\Features;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware "feature:key[,key2]" — 404s with a JSON message when any listed feature is off. */
class RequireFeature
{
    public function handle(Request $request, Closure $next, string ...$keys): Response
    {
        foreach ($keys as $key) {
            if (! Features::enabled($key)) {
                return response()->json([
                    'message' => 'This feature is currently unavailable.',
                    'error' => 'feature_disabled',
                    'feature' => $key,
                ], 404);
            }
        }

        return $next($request);
    }
}

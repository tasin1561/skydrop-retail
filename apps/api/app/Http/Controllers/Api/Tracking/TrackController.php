<?php

namespace App\Http\Controllers\Api\Tracking;

use App\Http\Controllers\Controller;
use App\Services\Tracking\EventBuilder;
use App\Services\Tracking\TrackingService;
use App\Services\Tracking\TrackingSettings;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TrackController extends Controller
{
    /**
     * POST /track: a browser event mirrored server-side with the same eventId (Meta / TikTok dedupe on it).
     * Public; when a Sanctum bearer token is sent, the signed-in shopper's phone/email are added (SHA-256).
     */
    public function store(Request $request, TrackingService $tracking): JsonResponse
    {
        $id = ['nullable', 'string', 'max:300'];
        $d = $request->validate([
            'event' => ['required', 'string', Rule::in(EventBuilder::BROWSER_EVENTS)],
            'eventId' => ['required', 'string', 'max:100', 'regex:/^[A-Za-z0-9_.:\-]+$/'],
            'url' => ['nullable', 'string', 'max:1000'],
            'referrer' => ['nullable', 'string', 'max:1000'],
            'value' => ['nullable', 'numeric', 'min:0', 'max:100000000'],
            'search' => ['nullable', 'string', 'max:200'],
            'orderId' => ['nullable', 'string', 'max:20'],
            'items' => ['nullable', 'array', 'max:50'],
            'items.*.id' => ['required', 'string', 'max:60'],
            'items.*.title' => ['nullable', 'string', 'max:500'],
            'items.*.brand' => ['nullable', 'string', 'max:120'],
            'items.*.category' => ['nullable', 'string', 'max:120'],
            'items.*.price' => ['nullable', 'numeric', 'min:0'],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:99'],
            'fbp' => $id, 'fbc' => $id, 'ttp' => $id, 'ttclid' => $id, 'gclid' => $id, 'gaClientId' => $id,
            'user' => ['nullable', 'array'],
            'user.phone' => ['nullable', 'string', 'max:30'],
            'user.email' => ['nullable', 'string', 'max:200'],
        ]);

        if (TrackingSettings::driver() === 'off') {
            return response()->json(['ok' => true, 'queued' => 0], 202);
        }
        $event = EventBuilder::fromTrack($d, $request->ip(), $request->userAgent(), $request->user('sanctum'));
        $rows = $tracking->dispatch($event);

        return response()->json(['ok' => true, 'queued' => count($rows)], 202);
    }

    /** GET /tracking/config: public pixel / tag ids for the storefront (admin-editable). */
    public function config(): JsonResponse
    {
        return response()->json(TrackingSettings::publicConfig())->header('Cache-Control', 'public, max-age=300');
    }
}

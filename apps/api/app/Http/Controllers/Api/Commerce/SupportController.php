<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\SupportRequest;
use App\Services\Commerce\CommerceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class SupportController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $items = SupportRequest::query()->where('user_id', $request->user()->id)->latest('id')->get();

        return response()->json(['items' => $items->map->toApi()->values()]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(SupportRequest::TYPES)],
            'message' => ['required', 'string', 'min:5', 'max:3000'],
            'photos' => ['nullable', 'array', 'max:6'],
            'photos.*' => ['url', 'max:1000'],
        ]);
        $order = Order::query()->find(strtoupper($id));
        if (! $order || $order->user_id !== $request->user()->id) {
            abort(404, 'Order not found.');
        }
        if (in_array($data['type'], ['return', 'damaged', 'wrong_item'], true)) {
            $days = (int) config('retail.checkout.return_window_days', 3);
            if ($order->status !== 'delivered' || ! $order->delivered_at) {
                throw new CommerceException('Returns and delivery issues can be reported once the order is delivered.', 422, [], 'not_delivered');
            }
            if ($order->delivered_at->lt(now()->subDays($days))) {
                throw new CommerceException("Returns are accepted within {$days} days of delivery.", 422, [], 'window_closed');
            }
        }
        $req = SupportRequest::query()->create([
            'order_id' => $order->id, 'user_id' => $request->user()->id, 'type' => $data['type'],
            'message' => $data['message'], 'photos' => $data['photos'] ?? [], 'status' => 'open',
        ]);

        return response()->json(['request' => $req->toApi()], 201);
    }
}

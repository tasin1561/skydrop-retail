<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\ProductReview;
use App\Services\Commerce\CommerceException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    /** Approved customer reviews (plus the caller's own pending review when signed in). */
    public function index(Request $request, string $id): JsonResponse
    {
        $userId = $request->user('sanctum')?->id;
        $q = ProductReview::query()->with('user')->where('product_id', $id)
            ->where(fn ($q) => $q->where('status', 'approved')->when($userId, fn ($q) => $q->orWhere('user_id', $userId)));
        $items = $q->latest('id')->limit(100)->get();
        $approved = $items->where('status', 'approved');

        return response()->json([
            'items' => $items->map->toApi()->values(),
            'count' => $approved->count(),
            'average' => $approved->count() ? round($approved->avg('rating'), 2) : null,
            'breakdown' => collect([5, 4, 3, 2, 1])->map(fn ($r) => ['rating' => $r, 'count' => $approved->where('rating', $r)->count()])->all(),
            'canReview' => $userId ? (bool) $this->deliveredOrderId($userId, $id) : false,
        ]);
    }

    public function store(Request $request, string $id): JsonResponse
    {
        $data = $request->validate([
            'rating' => ['required', 'integer', 'between:1,5'],
            'title' => ['nullable', 'string', 'max:120'],
            'body' => ['required_without:content', 'nullable', 'string', 'min:5', 'max:4000'],
            'content' => ['required_without:body', 'nullable', 'string', 'min:5', 'max:4000'],
            'photoUrl' => ['nullable', 'url:https', 'max:1000'],
        ]);
        $user = $request->user();
        $orderId = $this->deliveredOrderId($user->id, $id);
        if (! $orderId) {
            throw new CommerceException('Only shoppers who received this item can review it.', 403, [], 'not_a_buyer');
        }
        $review = ProductReview::query()->updateOrCreate(['user_id' => $user->id, 'product_id' => $id], [
            'order_id' => $orderId, 'rating' => $data['rating'], 'title' => $data['title'] ?? null,
            'body' => $data['body'] ?? $data['content'], 'photo_url' => $data['photoUrl'] ?? null, 'verified_purchase' => true, 'status' => 'pending',
        ]);

        return response()->json(['review' => $review->load('user')->toApi()], 201);
    }

    private function deliveredOrderId(int $userId, string $productId): ?string
    {
        return Order::query()->where('user_id', $userId)->where('status', 'delivered')
            ->whereHas('items', fn ($q) => $q->where('product_id', $productId))->latest('delivered_at')->value('id');
    }
}

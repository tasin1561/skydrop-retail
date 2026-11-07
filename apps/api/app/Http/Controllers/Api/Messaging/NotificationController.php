<?php

namespace App\Http\Controllers\Api\Messaging;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** In-app notification centre. */
class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['page' => ['sometimes', 'integer', 'min:1'], 'perPage' => ['sometimes', 'integer', 'min:1', 'max:50'], 'unread' => ['sometimes', 'boolean']]);
        $q = UserNotification::query()->where('user_id', $request->user()->id)
            ->when($request->boolean('unread'), fn ($q) => $q->whereNull('read_at'))
            ->latest('id');
        $page = $q->paginate((int) ($data['perPage'] ?? 20));

        return response()->json([
            'items' => collect($page->items())->map->toApi()->values(),
            'unreadCount' => $this->unread($request),
            'page' => $page->currentPage(), 'perPage' => $page->perPage(), 'total' => $page->total(), 'hasMore' => $page->hasMorePages(),
        ]);
    }

    public function read(Request $request, int $id): JsonResponse
    {
        $n = $this->find($request, $id);
        if (! $n->read_at) {
            $n->forceFill(['read_at' => now()])->save();
        }

        return response()->json(['item' => $n->toApi(), 'unreadCount' => $this->unread($request)]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $n = UserNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->update(['read_at' => now()]);

        return response()->json(['ok' => true, 'marked' => $n, 'unreadCount' => 0]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $this->find($request, $id)->delete();

        return response()->json(['ok' => true, 'unreadCount' => $this->unread($request)]);
    }

    private function find(Request $request, int $id): UserNotification
    {
        return UserNotification::query()->where('user_id', $request->user()->id)->findOrFail($id);
    }

    private function unread(Request $request): int
    {
        return UserNotification::query()->where('user_id', $request->user()->id)->whereNull('read_at')->count();
    }
}

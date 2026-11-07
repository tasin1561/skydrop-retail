<?php

namespace App\Http\Controllers\Api\Fulfilment;

use App\Http\Controllers\Controller;
use App\Models\Fulfilment\ReturnRequest;
use App\Models\Fulfilment\Upload;
use App\Models\Order;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Payments\PaymentService;
use App\Services\Fulfilment\OrderTracking;
use App\Services\Fulfilment\ReturnService;
use App\Services\Fulfilment\UnboxingService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Customer-facing fulfilment API: uploads, tracking map, unboxing approval, returns, paying a price difference. */
class FulfilmentController extends Controller
{
    // ---- uploads (private disk, signed URLs) ----

    public function upload(Request $request): JsonResponse
    {
        $request->validate(['file' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp,heic,heif', 'max:'.(\App\Support\Limits::int('uploads.image_mb') * 1024)]]); // System → Limits
        $file = $request->file('file');
        $path = $file->storeAs('uploads/'.$request->user()->id, Str::uuid().'.'.($file->guessExtension() ?: 'jpg'), 'local');
        $u = Upload::query()->create([
            'user_id' => $request->user()->id, 'disk' => 'local', 'path' => $path, 'mime' => (string) $file->getMimeType(),
            'size' => (int) $file->getSize(), 'purpose' => 'return',
        ]);

        return response()->json(['upload' => $u->toApi()], 201);
    }

    public function showUpload(Upload $upload): StreamedResponse
    {
        abort_unless(Storage::disk($upload->disk)->exists($upload->path), 404);

        return Storage::disk($upload->disk)->response($upload->path, null, ['Content-Type' => $upload->mime, 'Cache-Control' => 'private, max-age=3600']);
    }

    // ---- tracking ----

    public function tracking(Request $request, string $id, OrderTracking $tracking): JsonResponse
    {
        return response()->json(['tracking' => $tracking->toApi($this->own($request, $id))]);
    }

    // ---- unboxing ----

    public function unboxing(Request $request, string $id, UnboxingService $unboxing): JsonResponse
    {
        $order = $this->own($request, $id);
        if (! $order->unboxing) {
            abort(404, 'This order has no unboxing check.');
        }

        return response()->json(['unboxing' => $unboxing->toApi($order)]);
    }

    public function approveUnboxing(Request $request, string $id, UnboxingService $unboxing): JsonResponse
    {
        $order = $unboxing->approve($this->own($request, $id), 'customer');

        return response()->json(['unboxing' => $unboxing->toApi($order), 'order' => $order->toApi()]);
    }

    public function unboxingProblem(Request $request, string $id, UnboxingService $unboxing): JsonResponse
    {
        $data = $request->validate([
            'reason' => ['required', Rule::in(UnboxingService::PROBLEM_REASONS)],
            'note' => ['nullable', 'string', 'max:1000'],
        ]);
        $order = $unboxing->reportProblem($this->own($request, $id), $request->user(), $data['reason'], $data['note'] ?? null);
        $case = ReturnRequest::query()->where('order_id', $order->id)->latest('id')->first();

        return response()->json(['unboxing' => $unboxing->toApi($order), 'return' => $case?->toApi()]);
    }

    // ---- returns ----

    public function returns(Request $request): JsonResponse
    {
        $items = ReturnRequest::query()->where('user_id', $request->user()->id)->latest('id')->limit(100)->get();

        return response()->json(['items' => $items->map->toApi()->values()]);
    }

    public function orderReturns(Request $request, string $id, ReturnService $returns): JsonResponse
    {
        $order = $this->own($request, $id);

        return response()->json([
            'eligibility' => $returns->eligibility($order),
            'items' => ReturnRequest::query()->where('order_id', $order->id)->latest('id')->get()->map->toApi()->values(),
        ]);
    }

    public function createReturn(Request $request, string $id, ReturnService $returns): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.orderItemId' => ['required', 'integer'],
            'items.*.qty' => ['nullable', 'integer', 'min:1', 'max:50'],
            'reason' => ['required', Rule::in(array_keys(ReturnRequest::REASONS))],
            'note' => ['nullable', 'string', 'max:1000'],
            'photos' => ['nullable', 'array', 'max:'.\App\Support\Limits::int('uploads.max_files')], // System → Limits
            'photos.*' => ['integer'],
            'resolution' => ['required', Rule::in(array_keys(ReturnRequest::RESOLUTIONS))],
        ]);
        $r = $returns->create($this->own($request, $id), $request->user(), $data);

        return response()->json(['return' => $r->toApi()], 201);
    }

    // ---- extra charge (supplier price rise / weight) ----

    public function payDifference(Request $request, string $id, PaymentService $payments): JsonResponse
    {
        $order = $this->own($request, $id);
        if ($order->status === 'cancelled' || $order->balanceBdt() <= 0) {
            throw new CommerceException('Nothing is due on this order.', 409, [], 'nothing_due');
        }
        if ($order->payment_method === 'advance50') {
            throw new CommerceException('The difference is collected by the Skydrop rider with the rest of your balance.', 409, [], 'collected_on_delivery');
        }

        return response()->json(['order' => $order->toApi(), 'payment' => $payments->start($order)]);
    }

    private function own(Request $request, string $id): Order
    {
        $order = Order::query()->with(['items', 'events'])->find(strtoupper($id));
        if (! $order || $order->user_id !== $request->user()->id) {
            abort(404, 'Order not found.');
        }

        return $order;
    }
}

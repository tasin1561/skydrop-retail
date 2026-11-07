<?php

namespace App\Http\Controllers\Api\Commerce;

use App\Http\Controllers\Controller;
use App\Models\Address;
use App\Services\Commerce\AddressService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AddressController extends Controller
{
    public function __construct(private AddressService $addresses) {}

    public function index(Request $request): JsonResponse
    {
        $items = $request->user()->addresses()->orderByDesc('is_default')->latest('id')->get();

        return response()->json(['items' => $items->map->toApi()->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $a = $this->addresses->create($request->user(), $request->except('id'));

        return response()->json(['address' => $a->toApi()], 201);
    }

    public function update(Request $request, string $id): JsonResponse
    {
        $a = $this->find($request, $id);

        return response()->json(['address' => $this->addresses->update($a, $request->except('id'))->toApi()]);
    }

    public function destroy(Request $request, string $id): JsonResponse
    {
        $this->addresses->delete($this->find($request, $id));

        return response()->json(['ok' => true]);
    }

    private function find(Request $request, string $id): Address
    {
        return $request->user()->addresses()->findOrFail($id);
    }
}

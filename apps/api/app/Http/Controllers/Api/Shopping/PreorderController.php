<?php

namespace App\Http\Controllers\Api\Shopping;

use App\Http\Controllers\Api\Commerce\OrderController;
use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Services\Commerce\AddressService;
use App\Services\Shopping\PreorderService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class PreorderController extends Controller
{
    /** GET products/{id}/preorder (token optional) */
    public function show(Request $request, string $id, PreorderService $preorders): JsonResponse
    {
        return response()->json($preorders->info(Product::query()->findOrFail($id), $request->user('sanctum')));
    }

    /** POST preorders */
    public function store(Request $request, PreorderService $preorders): JsonResponse
    {
        $address = [];
        foreach (AddressService::rules() as $k => $r) {
            $address['address.'.$k] = array_map(fn ($x) => $x === 'required' ? 'required_without:addressId' : $x, $r);
        }
        $data = $request->validate([
            'productId' => ['required', 'string', 'max:40', 'exists:products,id'],
            'variant' => ['nullable', 'string', 'max:255'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:10'],
            'addressId' => ['nullable', 'integer', 'required_without:address'],
            'address' => ['nullable', 'array', 'required_without:addressId'],
            'payment' => ['required', Rule::in(['bkash', 'nagad', 'card'])],
            'returnUrl' => ['nullable', 'string', 'max:500', function ($attr, $value, $fail) {
                if (! OrderController::allowedReturnUrl($value)) {
                    $fail('returnUrl must point to the storefront.');
                }
            }],
        ] + $address);
        $r = $preorders->place($request->user(), $data);

        return response()->json(['order' => $r['order']->toApi(), 'payment' => $r['payment']], 201);
    }
}

<?php

namespace App\Http\Controllers\Api\Shopping;

use App\Http\Controllers\Controller;
use App\Models\ProductRequest;
use App\Services\Commerce\Phone;
use App\Services\Shopping\ProductRequestService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProductRequestController extends Controller
{
    /** POST product-requests (token optional; guests must give a phone) */
    public function store(Request $request, ProductRequestService $requests): JsonResponse
    {
        $user = $request->user('sanctum');
        $data = $request->validate([
            'url' => ['nullable', 'url', 'max:2000', 'required_without:description'],
            'description' => ['nullable', 'string', 'max:2000', 'required_without:url'],
            'photoUrl' => ['nullable', 'url', 'max:1000'],
            'qty' => ['nullable', 'integer', 'min:1', 'max:100'],
            'budgetBdt' => ['nullable', 'integer', 'min:1', 'max:10000000'],
            'name' => ['nullable', 'string', 'max:80'],
            'phone' => [$user ? 'nullable' : 'required', 'string', 'max:20', function ($attr, $value, $fail) {
                if ($value && ! Phone::normalize($value)) {
                    $fail('Enter a valid Bangladeshi mobile number.');
                }
            }],
            'query' => ['nullable', 'string', 'max:200'],
            'source' => ['nullable', Rule::in(['web', 'search', 'header', 'app'])],
        ]);
        $req = $requests->create($user, $data);

        return response()->json(['request' => $req->toApi()], 201);
    }

    /** GET product-requests (auth) */
    public function index(Request $request, ProductRequestService $requests): JsonResponse
    {
        $items = ProductRequest::query()->where('user_id', $request->user()->id)->latest('id')->limit(50)->get()
            ->map(fn ($r) => $requests->sync($r)->toApi());

        return response()->json(['items' => $items->values()]);
    }
}

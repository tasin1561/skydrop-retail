<?php

namespace App\Http\Controllers\Api\Shopping;

use App\Http\Controllers\Controller;
use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use App\Services\Commerce\AddressService;
use App\Services\Commerce\CommerceException;
use App\Services\Shopping\GroupBuyService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class GroupBuyController extends Controller
{
    private function rules(): array
    {
        $address = [];
        foreach (AddressService::rules() as $k => $r) {
            $address['address.'.$k] = array_map(fn ($x) => $x === 'required' ? 'required_with:address' : $x, $r);
        }

        return [
            'items' => ['nullable', 'array', 'max:30'],
            'items.*.productId' => ['required', 'string', 'max:40'],
            'items.*.variant' => ['nullable', 'string', 'max:255'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:20'],
            'addressId' => ['nullable', 'integer'],
            'address' => ['nullable', 'array'],
            'payment' => ['nullable', Rule::in(['bkash', 'nagad', 'card', 'advance50'])],
        ] + $address;
    }

    private function find(string $code): GroupBuy
    {
        return GroupBuy::query()->where('code', strtoupper($code))->firstOrFail();
    }

    /** GET groups (mine) */
    public function index(Request $request, GroupBuyService $groups): JsonResponse
    {
        $ids = GroupBuyMember::query()->where('user_id', $request->user()->id)->where('status', '!=', 'left')->pluck('group_buy_id');
        $items = GroupBuy::query()->whereIn('id', $ids)->latest('id')->limit(30)->get();

        return response()->json(['items' => $items->map(fn ($g) => $groups->toApi($g, $request->user()))->values()]);
    }

    /** POST groups { market, hours, items?, addressId?|address?, payment? } */
    public function store(Request $request, GroupBuyService $groups): JsonResponse
    {
        $data = $request->validate($this->rules() + [
            'market' => ['required', Rule::in(['amazon', 'flipkart', 'bigbasket', 'myntra'])],
            'hours' => ['nullable', 'integer', Rule::in(GroupBuyService::HOURS)],
        ]);
        $g = $groups->create($request->user(), $data['market'], (int) ($data['hours'] ?? 48), $data);

        return response()->json(['group' => $groups->toApi($g, $request->user())], 201);
    }

    /** GET groups/{code} (token optional) */
    public function show(Request $request, string $code, GroupBuyService $groups): JsonResponse
    {
        return response()->json(['group' => $groups->toApi($this->find($code), $request->user('sanctum'))]);
    }

    /** POST groups/{code}/join — join or update your items */
    public function join(Request $request, string $code, GroupBuyService $groups): JsonResponse
    {
        $data = $request->validate($this->rules());
        $g = $this->find($code);
        $groups->join($request->user(), $g, $data);

        return response()->json(['group' => $groups->toApi($g->refresh(), $request->user())]);
    }

    public function leave(Request $request, string $code, GroupBuyService $groups): JsonResponse
    {
        $g = $this->find($code);
        $groups->leave($request->user(), $g);

        return response()->json(['group' => $groups->toApi($g->refresh(), $request->user())]);
    }

    /** POST groups/{code}/close — owner closes early; member orders are placed now. */
    public function close(Request $request, string $code, GroupBuyService $groups): JsonResponse
    {
        $g = $this->find($code);
        if ($g->owner_id !== $request->user()->id) {
            throw new CommerceException('Only the person who started the group can close it.', 403, [], 'not_owner');
        }
        if ($g->status !== 'open') {
            throw new CommerceException('This group is closed.', 409, [], 'group_closed');
        }
        $result = $groups->close($g, 'Closed early by the organiser');

        return response()->json(['group' => $groups->toApi($g->refresh(), $request->user())] + $result);
    }

    public function cancel(Request $request, string $code, GroupBuyService $groups): JsonResponse
    {
        $g = $this->find($code);
        if ($g->owner_id !== $request->user()->id) {
            throw new CommerceException('Only the person who started the group can cancel it.', 403, [], 'not_owner');
        }
        $groups->cancel($g, 'Cancelled by the organiser');

        return response()->json(['group' => $groups->toApi($g->refresh(), $request->user())]);
    }
}

<?php

namespace App\Services\Shopping;

use App\Models\GroupBuy;
use App\Models\GroupBuyMember;
use App\Models\Product;
use App\Models\User;
use App\Services\Commerce\CheckoutService;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Notifier;
use App\Services\Commerce\OrderService;
use App\Services\PricingService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * Group buy: one store, friends join via a share link and add their own items, each pays for their own order.
 * At close we place one order per member (linked by orders.group_id) and ship them as one consolidated shipment:
 * the consolidated shipping is split by weight share and each member's saving is a discount on their order.
 * With a single member the order goes ahead normally, without a discount.
 */
class GroupBuyService
{
    public const HOURS = [24, 48, 72];

    public function __construct(private CheckoutService $checkout, private PricingService $pricing) {}

    public function create(User $owner, string $market, int $hours, array $member = []): GroupBuy
    {
        if (! in_array($hours, self::HOURS, true)) {
            throw CommerceException::field('hours', 'Choose 24, 48 or 72 hours.');
        }
        $group = DB::transaction(function () use ($owner, $market, $hours) {
            return GroupBuy::query()->create([
                'code' => $this->newCode(), 'market' => $market, 'owner_id' => $owner->id, 'status' => 'open',
                'min_members' => 2, 'closes_at' => now()->addHours($hours),
            ]);
        });
        $this->join($owner, $group, $member);

        return $group->refresh();
    }

    /** Join, or update your items/address/payment. $data: items[], addressId?, address?, payment? */
    public function join(User $user, GroupBuy $group, array $data): GroupBuyMember
    {
        if (! $group->isOpen()) {
            throw new CommerceException('This group is closed.', 409, [], 'group_closed');
        }
        if ($user->blocked_at) {
            throw new CommerceException('This account has been blocked. Please contact support.', 403, [], 'blocked');
        }
        $items = $this->cleanItems($group, $data['items'] ?? []);
        if (! empty($data['addressId']) && ! $user->addresses()->whereKey($data['addressId'])->exists()) {
            throw CommerceException::field('addressId', 'Choose one of your saved addresses.');
        }

        return GroupBuyMember::query()->updateOrCreate(
            ['group_buy_id' => $group->id, 'user_id' => $user->id],
            array_filter([
                'items' => $items, 'status' => 'joined',
                'address_id' => $data['addressId'] ?? null, 'address' => $data['address'] ?? null,
                'payment' => $data['payment'] ?? null,
            ], fn ($v) => $v !== null) + ['items' => $items],
        );
    }

    public function leave(User $user, GroupBuy $group): void
    {
        if ($group->owner_id === $user->id) {
            throw new CommerceException('You started this group. Cancel it instead of leaving.', 422, [], 'owner_cannot_leave');
        }
        if (! $group->isOpen()) {
            throw new CommerceException('This group is closed.', 409, [], 'group_closed');
        }
        GroupBuyMember::query()->where('group_buy_id', $group->id)->where('user_id', $user->id)->update(['status' => 'left']);
    }

    public function cancel(GroupBuy $group, string $note = 'Cancelled'): GroupBuy
    {
        if ($group->status !== 'open') {
            throw new CommerceException('Only an open group can be cancelled.', 409, [], 'group_closed');
        }
        $group->update(['status' => 'cancelled', 'closed_at' => now(), 'close_note' => $note]);

        return $group;
    }

    /**
     * Shipping maths for the current members (live prices).
     *
     * @return array{members: array<int, array{memberId: int, userId: int, weightKg: float, subtotalBdt: int, soloShippingBdt: int, shareBdt: int, savingBdt: int}>,
     *     soloShippingBdt: int, groupShippingBdt: int, savingBdt: int, eachSavesBdt: int, weightKg: float}
     */
    public function split(GroupBuy $group): array
    {
        $members = $group->members()->where('status', $group->status === 'open' ? 'joined' : 'ordered')->get()
            ->filter(fn (GroupBuyMember $m) => ! empty($m->items))->values();
        $rows = [];
        $all = [];
        foreach ($members as $m) {
            $lines = rescue(fn () => $this->checkout->lines($m->items), [], false);
            $simple = array_map(fn ($l) => ['unit_bdt' => $l['unit_bdt'], 'qty' => $l['qty'], 'weight_kg' => $l['weight_kg']], $lines);
            $all = array_merge($all, $simple);
            $rows[] = [
                'memberId' => $m->id, 'userId' => $m->user_id,
                'weightKg' => round(array_sum(array_map(fn ($l) => $l['weight_kg'] * $l['qty'], $simple)), 3),
                'subtotalBdt' => (int) round(array_sum(array_map(fn ($l) => $l['unit_bdt'] * $l['qty'], $simple))),
                'soloShippingBdt' => $simple ? $this->pricing->shipping($simple) : 0,
            ];
        }
        $groupShip = $all ? $this->pricing->shipping($all) : 0;
        $totalW = array_sum(array_column($rows, 'weightKg'));
        $grouped = count($rows) >= max(2, (int) $group->min_members);
        foreach ($rows as &$r) {
            $r['shareBdt'] = $grouped ? (int) round($totalW > 0 ? $groupShip * $r['weightKg'] / $totalW : $groupShip / max(1, count($rows))) : $r['soloShippingBdt'];
            $r['savingBdt'] = max(0, $r['soloShippingBdt'] - $r['shareBdt']);
        }
        unset($r);
        $solo = array_sum(array_column($rows, 'soloShippingBdt'));
        $saving = array_sum(array_column($rows, 'savingBdt'));

        return [
            'members' => $rows,
            'soloShippingBdt' => $solo,
            'groupShippingBdt' => $grouped ? $groupShip : $solo,
            'savingBdt' => $saving,
            'eachSavesBdt' => $rows ? (int) floor($saving / count($rows)) : 0,
            'weightKg' => round($totalW, 3),
            'grouped' => $grouped,
        ];
    }

    /**
     * Closes the group and places one order per member. Idempotent: an already closed group is returned unchanged.
     *
     * @return array{placed: int, failed: int}
     */
    public function close(GroupBuy $group, string $note = 'Closed'): array
    {
        $claimed = GroupBuy::query()->whereKey($group->id)->where('status', 'open')->update(['status' => 'closing']);
        if (! $claimed) {
            return ['placed' => 0, 'failed' => 0];
        }
        $group->refresh();
        $group->status = 'open'; // compute the split as if open (joined members)
        $split = $this->split($group);
        $byMember = collect($split['members'])->keyBy('memberId');
        $members = $group->members()->where('status', 'joined')->with('user')->get()->filter(fn ($m) => ! empty($m->items));

        if ($members->isEmpty()) {
            $group->forceFill(['status' => 'cancelled', 'closed_at' => now(), 'close_note' => 'Nobody added items'])->save();

            return ['placed' => 0, 'failed' => 0];
        }

        $placed = 0;
        $failed = 0;
        foreach ($members as $m) {
            $share = $split['grouped'] ? ($byMember[$m->id]['shareBdt'] ?? null) : null;
            try {
                $order = $this->placeFor($m, $group, $share);
                $m->update(['status' => 'ordered', 'order_id' => $order->id, 'share_bdt' => $share, 'error' => null]);
                rescue(fn () => app(Notifier::class)->paymentPending($order), null, true);
                $placed++;
            } catch (\Throwable $e) {
                Log::warning('[group-buy] member order failed: '.$e->getMessage(), ['group' => $group->code, 'member' => $m->id]);
                $m->update(['status' => 'failed', 'error' => mb_substr($e->getMessage(), 0, 250)]);
                $failed++;
            }
        }
        $group->forceFill([
            'status' => 'closed', 'closed_at' => now(), 'shipping_bdt' => $split['groupShippingBdt'],
            'close_note' => $split['grouped'] ? $note : $note.' (only one member: ordered without a group discount)',
        ])->save();

        return ['placed' => $placed, 'failed' => $failed];
    }

    private function placeFor(GroupBuyMember $m, GroupBuy $group, ?int $share): \App\Models\Order
    {
        $user = $m->user;
        $items = array_map(fn ($i) => ['productId' => $i['productId'], 'variant' => $i['variant'] ?? null, 'qty' => (int) $i['qty']], $m->items);
        $data = [
            'payment' => in_array($m->payment, ['bkash', 'nagad', 'card', 'advance50'], true) ? $m->payment : 'bkash',
            'group' => array_filter(['id' => $group->id, 'shareBdt' => $share], fn ($v) => $v !== null),
        ];
        if ($m->address_id && $user->addresses()->whereKey($m->address_id)->exists()) {
            $data['addressId'] = $m->address_id;
        } elseif ($m->address) {
            $data['address'] = $m->address;
        } elseif ($a = $user->addresses()->orderByDesc('is_default')->first()) {
            $data['addressId'] = $a->id;
        }

        try {
            return app(OrderService::class)->place($user, $data + ['items' => $items])['order'];
        } catch (CommerceException $e) {
            // Drop items that became unavailable and try once more with the rest.
            $gone = array_column($e->extra['unavailable'] ?? [], 'productId');
            $rest = array_values(array_filter($items, fn ($i) => ! in_array($i['productId'], $gone, true)));
            if (! $gone || ! $rest) {
                throw $e;
            }

            return app(OrderService::class)->place($user, $data + ['items' => $rest])['order'];
        }
    }

    /** @return list<array{productId: string, variant: ?string, qty: int}> */
    private function cleanItems(GroupBuy $group, array $items): array
    {
        $out = [];
        foreach ($items as $i) {
            $id = (string) ($i['productId'] ?? '');
            if ($id === '') {
                continue;
            }
            $key = $id.'|'.($i['variant'] ?? '');
            $out[$key] = ['productId' => $id, 'variant' => $i['variant'] ?? null, 'qty' => max(1, min(20, (int) ($i['qty'] ?? 1)) + (int) (($out[$key]['qty'] ?? 0)))];
        }
        $out = array_values($out);
        if ($out) {
            $products = Product::query()->whereIn('id', array_column($out, 'productId'))->get()->keyBy('id');
            foreach ($out as $i) {
                $p = $products[$i['productId']] ?? null;
                if (! $p) {
                    throw CommerceException::field('items', 'Unknown product: '.$i['productId']);
                }
                if ($p->market !== $group->market) {
                    throw CommerceException::field('items', 'This group orders from one store only. Remove "'.mb_strimwidth($p->title, 0, 40, '…').'".');
                }
            }
        }

        return $out;
    }

    private function newCode(): string
    {
        do {
            $code = strtoupper(Str::random(8));
            $code = strtr($code, ['0' => 'Q', 'O' => 'P', '1' => 'K', 'I' => 'J', 'L' => 'M']);
        } while (GroupBuy::query()->where('code', $code)->exists());

        return $code;
    }

    /** Group JSON for the API (members anonymised to first names). */
    public function toApi(GroupBuy $group, ?User $viewer = null): array
    {
        $group->loadMissing(['members.user', 'owner']);
        $split = $this->split($group);
        $byMember = collect($split['members'])->keyBy('memberId');
        $visible = $group->members->filter(fn ($m) => $m->status !== 'left')->values();
        $mine = $viewer ? $visible->firstWhere('user_id', $viewer->id) : null;
        $first = fn (?User $u) => $u ? (Str::before(trim((string) $u->name), ' ') ?: 'Friend') : 'Friend';

        return [
            'id' => (string) $group->id,
            'code' => $group->code,
            'market' => $group->market,
            'status' => $group->status === 'open' && $group->closes_at->isPast() ? 'closing' : $group->status,
            'closesAt' => $group->closes_at->toIso8601String(),
            'closedAt' => $group->closed_at?->toIso8601String(),
            'closeNote' => $group->close_note,
            'minMembers' => (int) $group->min_members,
            'owner' => $first($group->owner),
            'isOwner' => $viewer && $viewer->id === $group->owner_id,
            'shareUrl' => rtrim((string) config('retail.web_url', ''), '/').'/group/'.$group->code,
            'members' => $visible->map(fn ($m) => [
                'name' => $first($m->user), 'isOwner' => $m->user_id === $group->owner_id, 'isYou' => $viewer && $m->user_id === $viewer->id,
                'items' => array_sum(array_column($m->items ?? [], 'qty')), 'weightKg' => $byMember[$m->id]['weightKg'] ?? 0,
                'savingBdt' => $byMember[$m->id]['savingBdt'] ?? 0, 'status' => $m->status,
            ])->all(),
            'totals' => array_diff_key($split, ['members' => 1]),
            'you' => $mine ? [
                'items' => $mine->items, 'addressId' => $mine->address_id ? (string) $mine->address_id : null, 'payment' => $mine->payment,
                'status' => $mine->status, 'orderId' => $mine->order_id, 'error' => $mine->error,
                'savingBdt' => $byMember[$mine->id]['savingBdt'] ?? 0, 'shareBdt' => $byMember[$mine->id]['shareBdt'] ?? null,
                'soloShippingBdt' => $byMember[$mine->id]['soloShippingBdt'] ?? null,
            ] : null,
        ];
    }
}

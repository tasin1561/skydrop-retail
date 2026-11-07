<?php

namespace App\Services\Money;

use App\Models\GiftCard;
use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\CommerceException;
use App\Services\Commerce\Notifier;
use App\Services\Commerce\Payments\PaymentService;
use App\Services\Commerce\Phone;
use App\Services\Messaging\Messenger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

/**
 * Gift cards: bought by customers (paid order of kind gift_card), issued by staff, or bulk-generated as promo cards.
 * Redeeming moves the whole balance into the redeemer's Skydrop Wallet (reason gift_card).
 */
class GiftCardService
{
    private const ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789'; // no 0/O/1/I

    public function __construct(private WalletService $wallet) {}

    /** @return list<int> */
    public function presets(): array
    {
        return array_values(array_map('intval', (array) MoneySettings::get('gift_cards.presets', [500, 1000, 2000, 5000])));
    }

    /** @return array<string, string> */
    public function designs(): array
    {
        return (array) config('money.gift_cards.designs', ['classic' => 'Skydrop classic']);
    }

    public function config(): array
    {
        return [
            'presets' => $this->presets(), 'minBdt' => MoneySettings::int('gift_cards.min_bdt'), 'maxBdt' => MoneySettings::int('gift_cards.max_bdt'),
            'expiryMonths' => MoneySettings::int('gift_cards.expiry_months'),
            'designs' => collect($this->designs())->map(fn ($label, $key) => ['key' => $key, 'label' => $label])->values()->all(),
        ];
    }

    public function newCode(): string
    {
        do {
            $code = '';
            for ($i = 0; $i < 16; $i++) {
                $code .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
        } while (GiftCard::query()->where('code', $code)->exists());

        return $code;
    }

    /** Customer purchase: a pending card + an order of kind gift_card, then the normal payment flow. */
    public function purchase(User $user, array $data): array
    {
        $amount = (int) $data['amountBdt'];
        $min = MoneySettings::int('gift_cards.min_bdt');
        $max = MoneySettings::int('gift_cards.max_bdt');
        if ($amount < $min || $amount > $max) {
            throw CommerceException::field('amountBdt', 'Choose an amount between ৳'.number_format($min).' and ৳'.number_format($max).'.');
        }
        $design = array_key_exists($data['design'] ?? '', $this->designs()) ? $data['design'] : 'classic';

        [$card, $order] = DB::transaction(function () use ($user, $data, $amount, $design) {
            $card = GiftCard::query()->create([
                'code' => $this->newCode(), 'initial_bdt' => $amount, 'balance_bdt' => $amount, 'purchaser_user_id' => $user->id,
                'recipient_name' => $data['recipientName'] ?? null,
                'recipient_phone' => ! empty($data['recipientPhone']) ? (Phone::normalize($data['recipientPhone']) ?? $data['recipientPhone']) : null,
                'recipient_email' => $data['recipientEmail'] ?? null, 'message' => $data['message'] ?? null, 'design' => $design,
                'source' => 'purchase', 'status' => 'pending',
            ]);
            $order = app(DigitalOrders::class)->createOrder($user, 'gift_card', $amount, $data['payment'], ['gift_card_id' => $card->id], $data['returnUrl'] ?? null);
            $card->forceFill(['order_id' => $order->id])->save();

            return [$card, $order];
        });
        $payment = app(PaymentService::class)->start($order);

        return ['giftCard' => $card->refresh(), 'order' => $order->refresh(), 'payment' => $payment];
    }

    /** The gift card order was paid: activate and deliver. Idempotent. */
    public function activateFromOrder(Order $order): ?GiftCard
    {
        $card = GiftCard::query()->find($order->meta['gift_card_id'] ?? 0);
        if (! $card || $card->status !== 'pending') {
            return $card;
        }
        $card->forceFill(['status' => 'active', 'expires_at' => $this->expiry()])->save();
        $this->deliver($card);

        return $card;
    }

    /** Staff-issued card (active immediately). */
    public function issue(float $amount, array $data = [], string $source = 'admin', ?string $batch = null): GiftCard
    {
        if ($amount <= 0) {
            throw CommerceException::field('amount', 'Amount must be more than zero.');
        }
        $card = GiftCard::query()->create([
            'code' => $this->newCode(), 'initial_bdt' => $amount, 'balance_bdt' => $amount, 'source' => $source, 'status' => 'active',
            'recipient_name' => $data['recipient_name'] ?? null,
            'recipient_phone' => ! empty($data['recipient_phone']) ? (Phone::normalize($data['recipient_phone']) ?? $data['recipient_phone']) : null,
            'recipient_email' => $data['recipient_email'] ?? null, 'message' => $data['message'] ?? null,
            'design' => $data['design'] ?? 'classic', 'batch' => $batch,
            'expires_at' => array_key_exists('expires_at', $data) ? $data['expires_at'] : $this->expiry(),
        ]);
        if (! empty($data['deliver'])) {
            $this->deliver($card);
        }

        return $card;
    }

    /** @return list<GiftCard> */
    public function bulk(int $count, float $amount, mixed $expiresAt, ?string $batch = null): array
    {
        $batch ??= 'PROMO-'.now()->format('ymd-His');
        $out = [];
        DB::transaction(function () use ($count, $amount, $expiresAt, $batch, &$out) {
            for ($i = 0; $i < min(5000, max(1, $count)); $i++) {
                $out[] = $this->issue($amount, ['expires_at' => $expiresAt], 'promo', $batch);
            }
        });

        return $out;
    }

    public function void(GiftCard $card, string $reason): GiftCard
    {
        if (in_array($card->status, ['redeemed', 'void'], true)) {
            throw new CommerceException('This card is already '.$card->status.'.', 409);
        }
        $card->forceFill(['status' => 'void', 'void_reason' => mb_substr($reason, 0, 255)])->save();

        return $card;
    }

    /** Moves the card balance into the user's wallet. */
    public function redeem(User $user, string $rawCode): array
    {
        $code = GiftCard::normalize($rawCode);
        if (strlen($code) !== 16) {
            throw CommerceException::field('code', 'Enter the 16-character gift card code.');
        }

        return DB::transaction(function () use ($user, $code) {
            $card = GiftCard::query()->where('code', $code)->lockForUpdate()->first();
            $msg = match (true) {
                ! $card, $card->status === 'pending' => 'This gift card code is not valid.',
                $card->status === 'redeemed' => 'This gift card has already been redeemed.',
                $card->status === 'void' => 'This gift card is no longer valid.',
                $card->status === 'expired', $card->isExpired() => 'This gift card has expired.',
                $card->balance_bdt <= 0 => 'This gift card has no balance left.',
                default => null,
            };
            if ($msg) {
                if ($card && $card->status === 'active' && $card->isExpired()) {
                    $card->forceFill(['status' => 'expired'])->save();
                }
                throw CommerceException::field('code', $msg);
            }
            $amount = (float) $card->balance_bdt;
            $row = $this->wallet->credit($user, $amount, 'gift_card', 'Gift card ••••'.substr($card->code, -4), null, null, ['gift_card_id' => $card->id]);
            $card->forceFill(['balance_bdt' => 0, 'status' => 'redeemed', 'redeemed_by_user_id' => $user->id, 'redeemed_at' => now()])->save();

            return ['amountBdt' => (int) round($amount), 'balanceBdt' => (int) floor($row->balance_after_bdt), 'giftCard' => $card->toApi()];
        });
    }

    public function deliver(GiftCard $card): void
    {
        $from = $card->purchaser?->name ?? 'Skydrop Retail';
        $text = ($card->recipient_name ? "Hi {$card->recipient_name}, " : '').$from.' sent you a Skydrop gift card of ৳'.number_format($card->initial_bdt)
            .'. Code: '.$card->formatted().'. Redeem at '.Messenger::webUrl('/gift-cards?redeem=1')
            .($card->expires_at ? ' before '.$card->expires_at->format('j M Y') : '').'.'.($card->message ? ' "'.mb_strimwidth($card->message, 0, 120, '…').'"' : '');

        rescue(function () use ($card, $text) {
            $recipient = $card->recipient_phone ? User::query()->where('phone', $card->recipient_phone)->first() : null;
            if ($recipient) {
                app(Notifier::class)->custom($recipient, $text, 'both');
            } elseif ($card->recipient_phone && $card->purchaser) {
                app(Messenger::class)->raw($card->purchaser, ['sms'], 'Skydrop gift card', $text, 'transactional', ['phone' => $card->recipient_phone, 'event' => 'gift_card']);
            }
            if ($card->recipient_email && (! $recipient || $recipient->email !== $card->recipient_email)) {
                Mail::raw($text, fn ($m) => $m->to($card->recipient_email)->subject('You received a Skydrop gift card'));
            }
            if ($card->purchaser) {
                app(Notifier::class)->custom($card->purchaser, 'Your Skydrop gift card ('.$card->formatted().', ৳'.number_format($card->initial_bdt)
                    .') is ready'.($card->recipient_name ? ' and was sent to '.$card->recipient_name : '').'.', 'inapp');
            }
        }, null, true);
        $card->forceFill(['delivered_at' => now()])->save();
    }

    private function expiry(): ?Carbon
    {
        $m = MoneySettings::int('gift_cards.expiry_months');

        return $m > 0 ? now()->addMonthsNoOverflow($m)->endOfDay() : null;
    }

    public static function batchName(): string
    {
        return 'PROMO-'.Str::upper(Str::random(5));
    }
}

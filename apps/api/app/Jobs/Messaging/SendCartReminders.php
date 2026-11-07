<?php

namespace App\Jobs\Messaging;

use App\Models\Cart;
use App\Models\Order;
use App\Services\Commerce\Notifier;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Abandoned cart: push after 1 hour, then SMS/email after 24 hours (marketing: opt-outs and quiet hours apply).
 * Stops once the shopper orders (Order created marks the cart converted) or the cart changes (clock restarts).
 */
class SendCartReminders implements ShouldQueue
{
    use Queueable;

    public function handle(Notifier $notifier): void
    {
        $r = fn (string $k, int $d) => (int) MessagingSettings::get('reminders.'.$k, $d);
        $first = now()->subMinutes($r('cart_push_after_minutes', 60));
        $second = now()->subHours($r('cart_followup_after_hours', 24));
        $oldest = now()->subDays($r('cart_max_age_days', 7));

        $base = fn () => Cart::query()->with('user')->whereNull('converted_at')->where('item_count', '>', 0)->where('updated_at', '>=', $oldest);

        foreach ($base()->whereNull('reminded_1h_at')->where('updated_at', '<=', $first)->where('updated_at', '>', $second)->limit(500)->get() as $cart) {
            $this->remind($cart, '1h', 'reminded_1h_at', $notifier);
        }
        foreach ($base()->whereNull('reminded_24h_at')->where('updated_at', '<=', $second)->limit(500)->get() as $cart) {
            $this->remind($cart, '24h', 'reminded_24h_at', $notifier);
        }
    }

    private function remind(Cart $cart, string $stage, string $column, Notifier $notifier): void
    {
        $stamp = function (array $attrs) use ($cart) {
            $cart->timestamps = false; // keep updated_at as the "last cart change" clock
            $cart->forceFill($attrs)->save();
        };
        if (! $cart->user || $cart->user->blocked_at) {
            $stamp([$column => now()]);

            return;
        }
        if (Order::query()->where('user_id', $cart->user_id)->where('created_at', '>=', $cart->updated_at)->exists()) {
            $stamp(['converted_at' => now()]);

            return;
        }
        $notifier->cartReminder($cart, $stage);
        $stamp($stage === '24h' ? [$column => now(), 'reminded_1h_at' => $cart->reminded_1h_at ?? now()] : [$column => now()]);
    }
}

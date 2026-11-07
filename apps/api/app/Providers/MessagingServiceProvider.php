<?php

namespace App\Providers;

use App\Console\Commands\Messaging\SmsBalance;
use App\Models\Cart;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use App\Services\Commerce\Notifier;
use App\Services\Messaging\Push\NullWebPushSender;
use App\Services\Messaging\Push\WebPushSender;
use Illuminate\Support\ServiceProvider;

// Messaging module wiring: web push binding, model hooks (payment failed, email verification, cart conversion).
class MessagingServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // TODO(web-push): bind a minishlink/web-push implementation once the package is installed.
        $this->app->bindIf(WebPushSender::class, NullWebPushSender::class);
    }

    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->commands([SmsBalance::class]);
        }

        // Payment failed → "payment failed" message with a retry link.
        Payment::updated(function (Payment $p) {
            if ($p->wasChanged('status') && $p->status === 'failed' && in_array($p->kind, ['full', 'advance'], true)) {
                $order = Order::query()->find($p->order_id);
                if ($order && $order->status !== 'cancelled' && ! in_array($order->payment_status, ['paid', 'partial'], true)) {
                    rescue(fn () => app(Notifier::class)->paymentFailed($order, $p));
                }
            }
        });

        // A shopper adds or changes their email → send a verification link.
        User::updated(function (User $u) {
            if ($u->wasChanged('email') && $u->email && ! $u->is_admin && ! $u->email_verified_at) {
                rescue(fn () => app(Notifier::class)->emailVerification($u));
            }
        });

        // Ordering stops abandoned-cart reminders.
        Order::created(function (Order $o) {
            if ($o->user_id) {
                Cart::query()->where('user_id', $o->user_id)->whereNull('converted_at')->update(['converted_at' => now()]);
            }
        });
    }
}

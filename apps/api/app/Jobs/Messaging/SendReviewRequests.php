<?php

namespace App\Jobs\Messaging;

use App\Models\Order;
use App\Models\ProductReview;
use App\Services\Commerce\Notifier;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;

/** Review request N days (default 3) after delivery, once per order, skipped when the shopper already reviewed. */
class SendReviewRequests implements ShouldQueue
{
    use Queueable;

    public function handle(Notifier $notifier): void
    {
        $days = (int) MessagingSettings::get('reminders.review_after_days', 3);
        Order::query()->with(['user', 'items'])->where('status', 'delivered')
            ->whereBetween('delivered_at', [now()->subDays($days + 7), now()->subDays($days)])
            ->limit(500)->get()
            ->each(function (Order $o) use ($notifier) {
                if ($this->reviewed($o) || ! Cache::add('msg:review_request:'.$o->id, 1, now()->addDays(30))) {
                    return;
                }
                $notifier->reviewRequest($o);
            });
    }

    private function reviewed(Order $o): bool
    {
        if (! class_exists(ProductReview::class) || ! Schema::hasColumn('product_reviews', 'user_id')) {
            return false;
        }

        return ProductReview::query()->where('user_id', $o->user_id)->whereIn('product_id', $o->items->pluck('product_id'))->exists();
    }
}

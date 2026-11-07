<?php

namespace App\Services\Commerce;

use App\Models\Order;
use App\Models\User;
use App\Services\Commerce\Sms\SmsManager;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

// Customer notifications over SMS (SMS_DRIVER) and email (Laravel mail; log mailer in dev).
class Notifier
{
    public function __construct(private SmsManager $sms) {}

    public function orderPlaced(Order $order): void
    {
        $this->toOrder($order, 'Order placed', "Skydrop Retail: order {$order->id} placed. Total ".$this->bdt($order->total_bdt).'. Track: '.$this->link($order));
    }

    public function paymentReceived(Order $order, float $amount): void
    {
        $due = $order->balanceBdt();
        $tail = $due > 0 ? ' Pay '.$this->bdt($due).' to the Skydrop rider on delivery.' : '';
        $this->toOrder($order, 'Payment received', 'Skydrop Retail: we received '.$this->bdt($amount)." for order {$order->id}.".$tail);
    }

    public function statusChanged(Order $order, string $status): void
    {
        $text = match ($status) {
            'purchased' => "Skydrop Retail: your items for order {$order->id} are bought in India and heading to our Kolkata hub.",
            'out_for_delivery' => "Skydrop Retail: order {$order->id} is out for delivery today.".($order->balanceBdt() > 0 ? ' Please keep '.$this->bdt($order->balanceBdt()).' ready.' : ''),
            'delivered' => "Skydrop Retail: order {$order->id} delivered. Thank you! Need help? Reply within 3 days from your account.",
            'cancelled' => "Skydrop Retail: order {$order->id} was cancelled.".($order->payment_status === 'refunded' ? ' Your refund is on its way.' : ''),
            default => null,
        };
        if ($text) {
            $this->toOrder($order, 'Order '.str_replace('_', ' ', $status), $text);
        }
    }

    public function priceDrop(User $user, string $title, int $priceBdt, string $productId): void
    {
        $url = rtrim((string) config('retail.web_url'), '/').'/p/'.rawurlencode($productId);
        $this->toUser($user, 'Price drop', 'Skydrop Retail: price drop! '.mb_strimwidth($title, 0, 60, '…').' is now '.$this->bdt($priceBdt).". {$url}");
    }

    public function backInStock(User $user, string $title, string $productId): void
    {
        $url = rtrim((string) config('retail.web_url'), '/').'/p/'.rawurlencode($productId);
        $this->toUser($user, 'Back in stock', 'Skydrop Retail: back in stock: '.mb_strimwidth($title, 0, 60, '…').". {$url}");
    }

    /** Free-form message from staff (admin panel). $channel: sms | email | both. Returns false if nothing could be sent. */
    public function custom(User $user, string $text, string $channel = 'sms'): bool
    {
        $phone = in_array($channel, ['sms', 'both'], true) ? $user->phone : null;
        $email = in_array($channel, ['email', 'both'], true) ? $user->email : null;
        if (! $phone && ! $email) {
            return false;
        }
        $this->deliver($phone, $email, 'Message from Skydrop Retail', $text);

        return true;
    }

    private function toOrder(Order $order, string $subject, string $text): void
    {
        $order->loadMissing('user');
        $phone = $order->address['phone'] ?? null;
        $phone = Phone::normalize($phone) ?? $order->user?->phone;
        $this->deliver($phone, $order->user?->email, $subject.' · '.$order->id, $text);
    }

    private function toUser(User $user, string $subject, string $text): void
    {
        $this->deliver($user->phone, $user->email, $subject, $text);
    }

    private function deliver(?string $phone, ?string $email, string $subject, string $text): void
    {
        try {
            if ($phone) {
                $this->sms->driver()->send($phone, $text);
            }
            if ($email) {
                Mail::raw($text, fn ($m) => $m->to($email)->subject('Skydrop Retail · '.$subject));
            }
        } catch (\Throwable $e) {
            Log::warning('[notifier] '.$e->getMessage());
        }
    }

    private function link(Order $order): string
    {
        return rtrim((string) config('retail.web_url'), '/').'/orders/'.$order->id;
    }

    private function bdt(float $n): string
    {
        return 'BDT '.number_format(round($n));
    }
}

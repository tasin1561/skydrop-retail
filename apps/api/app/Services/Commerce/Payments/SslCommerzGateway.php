<?php

namespace App\Services\Commerce\Payments;

use App\Models\Order;
use App\Models\Payment;
use App\Services\Money\EmiService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * SSLCommerz v4: session init -> redirect to GatewayPageURL -> success/fail/cancel browser POST + IPN POST
 * -> validate val_id with the validator API (never trust the posted status). Needs SSLC_STORE_ID / SSLC_STORE_PASSWORD.
 */
class SslCommerzGateway implements Gateway
{
    public function name(): string
    {
        return 'sslcommerz';
    }

    private function base(): string
    {
        $c = config('retail.payments.sslcommerz');

        return rtrim(config('retail.payments.mode') === 'live' ? $c['live_url'] : $c['sandbox_url'], '/');
    }

    private function creds(): array
    {
        $c = config('retail.payments.sslcommerz');
        if (empty($c['store_id']) || empty($c['store_password'])) {
            throw new RuntimeException('SSLCommerz credentials are not configured (SSLC_STORE_ID / SSLC_STORE_PASSWORD).');
        }

        return [$c['store_id'], $c['store_password']];
    }

    public static function tranId(Payment $payment): string
    {
        return $payment->order_id.'-P'.$payment->id;
    }

    public function initiate(Payment $payment, Order $order, string $callbackUrl): array
    {
        [$id, $pass] = $this->creds();
        $order->loadMissing('items', 'user');
        $a = $order->address;
        $res = Http::asForm()->timeout(30)->post($this->base().'/gwprocess/v4/api.php', [
            'store_id' => $id, 'store_passwd' => $pass,
            'total_amount' => number_format($payment->amount_bdt, 2, '.', ''), 'currency' => 'BDT',
            'tran_id' => self::tranId($payment),
            'success_url' => $callbackUrl.'&return=1', 'fail_url' => $callbackUrl.'&return=1', 'cancel_url' => $callbackUrl.'&return=1',
            'ipn_url' => $callbackUrl,
            'cus_name' => $a['name'] ?? $order->user?->name, 'cus_email' => $order->user?->email ?: 'customer@skydrop.retail',
            'cus_add1' => trim(($a['line'] ?? '').', '.($a['area'] ?? '')), 'cus_city' => $a['district'] ?? 'Dhaka',
            'cus_state' => $a['division'] ?? 'Dhaka', 'cus_postcode' => '1000', 'cus_country' => 'Bangladesh',
            'cus_phone' => $a['phone'] ?? $order->user?->phone,
            'shipping_method' => 'NO', 'num_of_item' => max(1, (int) $order->items->sum('qty')), // gift card / Plus orders have no items
            'product_name' => mb_substr($order->items->pluck('title')->implode(', '), 0, 250) ?: 'Skydrop Retail order',
            'product_category' => 'general', 'product_profile' => 'general',
            'value_a' => (string) $payment->id,
        ] + self::emiParams($order));
        if (! $res->successful() || $res->json('status') !== 'SUCCESS' || ! $res->json('GatewayPageURL')) {
            throw new RuntimeException('SSLCommerz session failed: '.$res->json('failedreason', $res->body()));
        }
        $payment->gateway_ref = $res->json('sessionkey');
        $payment->remember(['tran_id' => self::tranId($payment)]);
        $payment->save();

        return ['status' => 'redirect', 'redirectUrl' => $res->json('GatewayPageURL'), 'ref' => $payment->gateway_ref];
    }

    /**
     * Card EMI (order.payment_method = card_emi): emi_option=1 shows the EMI tab, emi_max_inst_option caps the tenure,
     * emi_selected_inst preselects the shopper's tenure and emi_allow_only=1 hides non-EMI options.
     */
    public static function emiParams(Order $order): array
    {
        if ($order->payment_method !== 'card_emi') {
            return [];
        }
        $tenure = (int) ($order->emi['tenure'] ?? 0);
        $max = max([$tenure, ...app(EmiService::class)->tenures()]);

        return ['emi_option' => 1, 'emi_max_inst_option' => $max, 'emi_selected_inst' => $tenure ?: $max, 'emi_allow_only' => 1];
    }

    public function confirm(Payment $payment, Request $request): GatewayResult
    {
        if ($request->input('tran_id') && $request->input('tran_id') !== self::tranId($payment)) {
            throw new InvalidCallback('tran_id does not match');
        }
        $valId = $request->input('val_id');
        if (! $valId) {
            return GatewayResult::failed('Card payment '.strtolower((string) $request->input('status', 'failed')).'.', ['callback' => $request->except(['store_passwd'])]);
        }
        [$id, $pass] = $this->creds();
        $res = Http::timeout(30)->get($this->base().'/validator/api/validationserverAPI.php', [
            'val_id' => $valId, 'store_id' => $id, 'store_passwd' => $pass, 'format' => 'json', 'v' => 1,
        ]);
        $body = $res->json() ?? [];
        $status = $body['status'] ?? null;
        if (! in_array($status, ['VALID', 'VALIDATED'], true) || ($body['tran_id'] ?? null) !== self::tranId($payment)) {
            return GatewayResult::failed('SSLCommerz validation failed ('.($status ?? 'no status').').', ['validation' => $body]);
        }
        if (abs((float) ($body['currency_amount'] ?? $body['amount'] ?? 0) - $payment->amount_bdt) > 0.01) {
            return GatewayResult::failed('Paid amount does not match the order.', ['validation' => $body]);
        }

        return GatewayResult::paid($body['bank_tran_id'] ?? $valId, ['validation' => $body, 'bank_tran_id' => $body['bank_tran_id'] ?? null], (float) $body['amount']);
    }

    public function paymentIdFrom(Request $request): ?int
    {
        $v = $request->input('value_a');
        if ($v && ctype_digit((string) $v)) {
            return (int) $v;
        }

        return preg_match('/-P(\d+)$/', (string) $request->input('tran_id'), $m) ? (int) $m[1] : null;
    }

    public function query(Payment $payment): GatewayResult
    {
        [$id, $pass] = $this->creds();
        $res = Http::timeout(30)->get($this->base().'/validator/api/merchantTransIDvalidationAPI.php', [
            'tran_id' => self::tranId($payment), 'store_id' => $id, 'store_passwd' => $pass, 'format' => 'json',
        ]);
        $row = collect($res->json('element') ?? [])->first(fn ($e) => in_array($e['status'] ?? '', ['VALID', 'VALIDATED'], true));

        return $row ? GatewayResult::paid($row['bank_tran_id'] ?? null, ['query' => $row], (float) ($row['amount'] ?? 0)) : GatewayResult::pending(['query' => $res->json()]);
    }

    public function refund(Payment $payment, float $amount, string $reason): GatewayResult
    {
        [$id, $pass] = $this->creds();
        $bankTran = $payment->payload['bank_tran_id'] ?? $payment->gateway_ref;
        $res = Http::timeout(30)->get($this->base().'/validator/api/merchantTransIDvalidationAPI.php', [
            'bank_tran_id' => $bankTran, 'refund_amount' => number_format($amount, 2, '.', ''),
            'refund_remarks' => mb_substr($reason, 0, 250), 'refe_id' => $payment->order_id,
            'store_id' => $id, 'store_passwd' => $pass, 'format' => 'json',
        ]);
        $body = $res->json() ?? [];
        if (($body['APIConnect'] ?? null) === 'DONE' && in_array($body['status'] ?? '', ['success', 'processing'], true)) {
            return new GatewayResult('refunded', $body['refund_ref_id'] ?? null, ['refund' => $body], null, $amount);
        }

        return GatewayResult::failed('SSLCommerz refund failed: '.($body['errorReason'] ?? $res->body()), ['refund' => $body]);
    }
}

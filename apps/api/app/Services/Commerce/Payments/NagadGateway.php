<?php

namespace App\Services\Commerce\Payments;

use App\Models\Order;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use RuntimeException;

/**
 * Nagad Payment Gateway (merchant checkout, API v-0.2.0).
 *  1. initialize: sensitiveData {merchantId, datetime, orderId, challenge} RSA-encrypted with NAGAD's public key,
 *     signed (SHA256withRSA) with OUR private key -> response sensitiveData (encrypted to our public key) gives
 *     paymentReferenceId + challenge.
 *  2. complete: sensitiveData {merchantId, orderId, currencyCode 050, amount, challenge} + merchantCallbackURL
 *     -> callBackUrl to redirect the shopper.
 *  3. callback (?payment_ref_id=&status=) -> verify with GET /api/dfs/verify/payment/{payment_ref_id}.
 *
 * TODO(credentials): needs NAGAD_MERCHANT_ID, NAGAD_MERCHANT_NUMBER, NAGAD_PUBLIC_KEY (Nagad's PG public key) and
 * NAGAD_PRIVATE_KEY (our merchant private key, PEM body or full PEM). Nagad whitelists the server IP; sandbox
 * access is only granted after the merchant agreement. Refunds are not part of the public API (TODO below).
 */
class NagadGateway implements Gateway
{
    public function name(): string
    {
        return 'nagad';
    }

    private function base(): string
    {
        $c = config('retail.payments.nagad');

        return rtrim(config('retail.payments.mode') === 'live' ? $c['live_url'] : $c['sandbox_url'], '/');
    }

    private function cfg(): array
    {
        $c = config('retail.payments.nagad');
        if (empty($c['merchant_id']) || empty($c['public_key']) || empty($c['private_key'])) {
            throw new RuntimeException('Nagad credentials are not configured (NAGAD_MERCHANT_ID / NAGAD_PUBLIC_KEY / NAGAD_PRIVATE_KEY).');
        }

        return $c;
    }

    private static function pem(string $key, string $type): string
    {
        if (str_contains($key, 'BEGIN')) {
            return str_replace('\n', "\n", $key);
        }

        return "-----BEGIN {$type}-----\n".chunk_split(preg_replace('/\s+/', '', $key), 64, "\n")."-----END {$type}-----\n";
    }

    private function encrypt(array $data): string
    {
        $pub = openssl_pkey_get_public(self::pem($this->cfg()['public_key'], 'PUBLIC KEY'));
        if (! $pub || ! openssl_public_encrypt(json_encode($data), $out, $pub, OPENSSL_PKCS1_PADDING)) {
            throw new RuntimeException('Nagad: could not encrypt sensitive data (check NAGAD_PUBLIC_KEY).');
        }

        return base64_encode($out);
    }

    private function sign(array $data): string
    {
        $priv = openssl_pkey_get_private(self::pem($this->cfg()['private_key'], 'PRIVATE KEY'));
        if (! $priv || ! openssl_sign(json_encode($data), $sig, $priv, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Nagad: could not sign (check NAGAD_PRIVATE_KEY).');
        }

        return base64_encode($sig);
    }

    private function decrypt(string $b64): array
    {
        $priv = openssl_pkey_get_private(self::pem($this->cfg()['private_key'], 'PRIVATE KEY'));
        if (! $priv || ! openssl_private_decrypt(base64_decode($b64), $out, $priv)) {
            throw new RuntimeException('Nagad: could not decrypt response.');
        }

        return json_decode($out, true) ?? [];
    }

    private function http()
    {
        return Http::timeout(30)->acceptJson()->withHeaders([
            'X-KM-Api-Version' => 'v-0.2.0', 'X-KM-IP-V4' => request()->ip() ?? '127.0.0.1', 'X-KM-Client-Type' => 'PC_WEB',
        ]);
    }

    public static function orderRef(Payment $payment): string
    {
        // Nagad orderId: alphanumeric, unique per attempt.
        return preg_replace('/[^A-Za-z0-9]/', '', $payment->order_id).'P'.$payment->id;
    }

    public function initiate(Payment $payment, Order $order, string $callbackUrl): array
    {
        $c = $this->cfg();
        $orderRef = self::orderRef($payment);
        $dt = now('Asia/Dhaka')->format('YmdHis');
        $sensitive = ['merchantId' => $c['merchant_id'], 'datetime' => $dt, 'orderId' => $orderRef, 'challenge' => Str::random(40)];

        $init = $this->http()->post($this->base()."/api/dfs/check-out/initialize/{$c['merchant_id']}/{$orderRef}", [
            'accountNumber' => $c['merchant_number'], 'dateTime' => $dt,
            'sensitiveData' => $this->encrypt($sensitive), 'signature' => $this->sign($sensitive),
        ]);
        if (! $init->successful() || ! $init->json('sensitiveData')) {
            throw new RuntimeException('Nagad initialize failed: '.$init->json('message', $init->body()));
        }
        // TODO(credentials): also verify $init->json('signature') with Nagad's public key once keys are issued.
        $plain = $this->decrypt($init->json('sensitiveData'));

        $complete = ['merchantId' => $c['merchant_id'], 'orderId' => $orderRef, 'currencyCode' => '050',
            'amount' => number_format($payment->amount_bdt, 2, '.', ''), 'challenge' => $plain['challenge'] ?? ''];
        $res = $this->http()->post($this->base().'/api/dfs/check-out/complete/'.($plain['paymentReferenceId'] ?? ''), [
            'sensitiveData' => $this->encrypt($complete), 'signature' => $this->sign($complete),
            'merchantCallbackURL' => $callbackUrl,
            'additionalMerchantInfo' => ['orderId' => $order->id],
        ]);
        if ($res->json('status') !== 'Success' || ! $res->json('callBackUrl')) {
            throw new RuntimeException('Nagad complete failed: '.$res->json('message', $res->body()));
        }
        $payment->gateway_ref = $plain['paymentReferenceId'] ?? null;
        $payment->remember(['orderRef' => $orderRef]);
        $payment->save();

        return ['status' => 'redirect', 'redirectUrl' => $res->json('callBackUrl'), 'ref' => $payment->gateway_ref];
    }

    public function confirm(Payment $payment, Request $request): GatewayResult
    {
        $ref = (string) $request->input('payment_ref_id');
        // Security: only the reference Nagad issued for THIS payment at initialize. A payment without one (initialize
        // failed) must not adopt a reference from the callback, or one paid Nagad transaction could settle other orders.
        if ($ref === '' || ! $payment->gateway_ref || ! hash_equals((string) $payment->gateway_ref, $ref)) {
            throw new InvalidCallback('payment_ref_id does not match');
        }

        return $this->query($payment);
    }

    public function paymentIdFrom(Request $request): ?int
    {
        return preg_match('/P(\d+)$/', (string) $request->input('order_id'), $m) ? (int) $m[1] : null;
    }

    public function query(Payment $payment): GatewayResult
    {
        $res = $this->http()->get($this->base().'/api/dfs/verify/payment/'.$payment->gateway_ref);
        $body = $res->json() ?? [];
        if (($body['status'] ?? null) === 'Success') {
            if (isset($body['orderId']) && (string) $body['orderId'] !== self::orderRef($payment)) {
                return GatewayResult::failed('Nagad payment belongs to another order.', ['verify' => $body]);
            }
            if (abs((float) ($body['amount'] ?? 0) - $payment->amount_bdt) > 0.01) {
                return GatewayResult::failed('Paid amount does not match the order.', ['verify' => $body]);
            }

            return GatewayResult::paid($body['issuerPaymentRefNo'] ?? $payment->gateway_ref, ['verify' => $body], (float) $body['amount']);
        }

        return in_array($body['status'] ?? '', ['Ready', 'InProgress'], true)
            ? GatewayResult::pending(['verify' => $body])
            : GatewayResult::failed('Nagad: '.($body['statusCode'] ?? $body['status'] ?? 'payment not completed'), ['verify' => $body]);
    }

    public function refund(Payment $payment, float $amount, string $reason): GatewayResult
    {
        // TODO(credentials): Nagad refunds are enabled per merchant (separate refund API / merchant portal).
        // Until then refunds are flagged for manual processing in the admin panel.
        return GatewayResult::pending(['manual_refund' => true, 'reason' => $reason], 'Nagad refund must be processed manually.');
    }
}

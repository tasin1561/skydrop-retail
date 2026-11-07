<?php

namespace App\Services\Fraud\Couriers;

use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Pathao customer delivery history ("customer success rate" in the Pathao merchant panel).
 *
 * Signs in with the merchant panel account: POST {merchant_url}/api/v1/login {username, password} → access_token,
 * then POST {merchant_url}/api/v1/user/success {phone} → {"data": {"customer": {"total_delivery", "successful_delivery"}}}.
 * The token is cached (encrypted) for 50 minutes and refreshed once on 401/403.
 * This is the panel's own API, not a published one: if Pathao changes it, checks fail softly (recorded, not thrown to checkout).
 */
class PathaoDriver implements CourierFraudDriver
{
    private const SESSION_MINUTES = 50;

    public function id(): string
    {
        return 'pathao';
    }

    public function enabled(): bool
    {
        $c = CourierCreds::get('pathao');

        return $c['enabled'] && filled($c['username'] ?? null) && filled($c['password'] ?? null);
    }

    public function check(string $phone): array
    {
        $c = CourierCreds::get('pathao');
        $phone = CourierCreds::localPhone($phone);

        $r = $this->lookup($c, $this->token($c), $phone);
        if (in_array($r->status(), [401, 403], true)) {
            CourierCreds::forgetSession($this->sessionKey($c));
            $r = $this->lookup($c, $this->token($c), $phone);
        }
        if (! $r->successful()) {
            throw new RuntimeException('Pathao HTTP '.$r->status());
        }

        return self::parse((array) $r->json());
    }

    /** Response → {total, delivered, cancelled, raw}. */
    public static function parse(array $j): array
    {
        $c = (array) ($j['data']['customer'] ?? []);
        $total = (int) ($c['total_delivery'] ?? 0);
        $ok = min($total, (int) ($c['successful_delivery'] ?? 0));

        return ['total' => $total, 'delivered' => $ok, 'cancelled' => max(0, $total - $ok), 'raw' => $j];
    }

    private function token(array $c): string
    {
        $key = $this->sessionKey($c);
        $s = CourierCreds::cachedSession($key);
        if (! empty($s['token'])) {
            return $s['token'];
        }
        $r = Http::acceptJson()->asJson()->timeout(10)
            ->post(rtrim((string) $c['merchant_url'], '/').'/api/v1/login', ['username' => $c['username'], 'password' => $c['password']]);
        $token = trim((string) ($r->json('access_token') ?? $r->json('data.access_token') ?? ''));
        if (! $r->successful() || $token === '') {
            throw new RuntimeException('Pathao login failed (HTTP '.$r->status().'): check the merchant username and password');
        }
        CourierCreds::putSession($key, ['token' => $token], self::SESSION_MINUTES);

        return $token;
    }

    private function lookup(array $c, string $token, string $phone): Response
    {
        return Http::acceptJson()->asJson()->withToken($token)->timeout(8)
            ->post(rtrim((string) $c['merchant_url'], '/').'/api/v1/user/success', ['phone' => $phone]);
    }

    private function sessionKey(array $c): string
    {
        return 'fraud:pathao:session:'.md5((string) $c['username']);
    }
}

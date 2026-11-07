<?php

namespace App\Services\Fraud\Couriers;

use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * CarryBee customer delivery history (the "fraud check" in the CarryBee merchant panel).
 *
 * Signs in like the merchant panel (NextAuth):
 *   GET  {login_url}/api/auth/csrf → csrfToken
 *   POST {login_url}/api/auth/callback/login (form: phone +8801…, password, csrfToken, callbackUrl)
 *   GET  {login_url}/api/auth/session → {accessToken, user: {selectedBusinessId}}
 * then GET {api_url}/businesses/{businessId}/fraud-check/{01XXXXXXXXX} → {"data": {"total_order", "cancelled_order", "success_rate"}}.
 * The session is cached (encrypted) for 50 minutes and refreshed once on 401/403.
 * This is the panel's own API, not a published one: if CarryBee changes it, checks fail softly.
 */
class CarryBeeDriver implements CourierFraudDriver
{
    private const SESSION_MINUTES = 50;

    private const UA = 'Mozilla/5.0 (compatible; SkydropRetail/1.0)';

    public function id(): string
    {
        return 'carrybee';
    }

    public function enabled(): bool
    {
        $c = CourierCreds::get('carrybee');

        return $c['enabled'] && filled($c['phone'] ?? null) && filled($c['password'] ?? null);
    }

    public function check(string $phone): array
    {
        $c = CourierCreds::get('carrybee');
        $phone = CourierCreds::localPhone($phone);

        $r = $this->lookup($c, $this->session($c), $phone);
        if (in_array($r->status(), [401, 403], true)) {
            CourierCreds::forgetSession($this->sessionKey($c));
            $r = $this->lookup($c, $this->session($c), $phone);
        }
        if (! $r->successful() || $r->json('error')) {
            throw new RuntimeException('CarryBee HTTP '.$r->status());
        }

        return self::parse((array) $r->json());
    }

    /** Response → {total, delivered, cancelled, raw}. */
    public static function parse(array $j): array
    {
        $d = (array) ($j['data'] ?? []);
        $total = (int) ($d['total_order'] ?? 0);
        $cancelled = min($total, (int) ($d['cancelled_order'] ?? 0));

        return ['total' => $total, 'delivered' => $total - $cancelled, 'cancelled' => $cancelled, 'raw' => $j];
    }

    /** @return array{token: string, business: string} */
    private function session(array $c): array
    {
        $key = $this->sessionKey($c);
        $s = CourierCreds::cachedSession($key);
        if (! empty($s['token']) && ! empty($s['business'])) {
            return $s;
        }
        $base = rtrim((string) $c['login_url'], '/');
        $jar = new CookieJar;
        $http = fn () => Http::withOptions(['cookies' => $jar])->timeout(10)
            ->withHeaders(['User-Agent' => self::UA, 'Accept' => 'application/json', 'Referer' => $base.'/login']);

        $csrf = (string) $http()->get($base.'/api/auth/csrf')->json('csrfToken');
        if ($csrf === '') {
            throw new RuntimeException('CarryBee login failed: no CSRF token');
        }
        $http()->asForm()->post($base.'/api/auth/callback/login', [
            'phone' => '+88'.CourierCreds::localPhone((string) $c['phone']),
            'password' => $c['password'],
            'csrfToken' => $csrf,
            'callbackUrl' => $base.'/login',
        ]);
        $session = $http()->get($base.'/api/auth/session');
        $token = (string) $session->json('accessToken');
        $business = (string) ($c['business_id'] ?: $session->json('user.selectedBusinessId'));
        if ($token === '' || $business === '') {
            throw new RuntimeException('CarryBee login failed: check the merchant phone and password');
        }
        $s = ['token' => $token, 'business' => $business];
        CourierCreds::putSession($key, $s, self::SESSION_MINUTES);

        return $s;
    }

    private function lookup(array $c, array $s, string $phone): Response
    {
        return Http::acceptJson()->withToken($s['token'])->withHeaders(['User-Agent' => self::UA])->timeout(8)
            ->get(rtrim((string) $c['api_url'], '/').'/businesses/'.rawurlencode($s['business']).'/fraud-check/'.rawurlencode($phone));
    }

    private function sessionKey(array $c): string
    {
        return 'fraud:carrybee:session:'.md5((string) $c['phone']);
    }
}

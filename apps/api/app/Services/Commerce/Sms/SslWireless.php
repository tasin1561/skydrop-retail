<?php

namespace App\Services\Commerce\Sms;

use App\Services\Commerce\Phone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Throwable;

/**
 * SSL Wireless ISMS v3. POST {url}/send-sms JSON { api_token, sid, msisdn, sms, csms_id } → { status: "SUCCESS", smsinfo: [{ reference_id }] }.
 * Balance: GET {url}/balance?api_token&sid. (The legacy generic "http" driver keeps working too.)
 */
class SslWireless extends AbstractGateway
{
    public function name(): string
    {
        return 'sslwireless';
    }

    public function configured(): bool
    {
        return (bool) $this->cfg('api_token') && (bool) $this->cfg('sid');
    }

    public function deliver(string $phone, string $message): SmsResult
    {
        if (! $this->configured()) {
            return $this->failed('SSLWIRELESS_API_TOKEN / SSLWIRELESS_SID not configured');
        }
        try {
            $res = Http::timeout((int) $this->cfg('timeout', 10))->acceptJson()->asJson()->post(rtrim($this->cfg('url'), '/').'/send-sms', [
                'api_token' => $this->cfg('api_token'), 'sid' => $this->cfg('sid'), 'msisdn' => Phone::msisdn($phone),
                'sms' => $message, 'csms_id' => Str::upper(Str::random(20)),
            ]);
        } catch (Throwable $e) {
            return $this->failed('HTTP error: '.$e->getMessage());
        }
        if ($res->successful() && strtoupper((string) $res->json('status')) === 'SUCCESS') {
            $ref = $res->json('smsinfo.0.reference_id') ?? $res->json('smsinfo.0.csms_id');

            return SmsResult::ok($this->name(), $ref ? (string) $ref : null);
        }

        return $this->failed('SSL Wireless: '.($res->json('error_message') ?? 'HTTP '.$res->status()), $res->body());
    }

    public function balance(): ?float
    {
        if (! $this->configured()) {
            return null;
        }
        try {
            $b = Http::timeout(10)->acceptJson()->get(rtrim($this->cfg('url'), '/').'/balance', ['api_token' => $this->cfg('api_token'), 'sid' => $this->cfg('sid')])->json('balance');

            return is_numeric($b) ? (float) $b : null;
        } catch (Throwable) {
            return null;
        }
    }
}

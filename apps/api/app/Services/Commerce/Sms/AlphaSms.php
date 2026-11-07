<?php

namespace App\Services\Commerce\Sms;

use App\Services\Commerce\Phone;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * Alpha SMS (api.sms.net.bd). POST {url}/sendsms { api_key, msg, to, sender_id? } → { error: 0, data: { request_id } }.
 * Balance: GET {url}/user/balance/?api_key → { error: 0, data: { balance } }.
 */
class AlphaSms extends AbstractGateway
{
    public function name(): string
    {
        return 'alpha';
    }

    public function configured(): bool
    {
        return (bool) $this->cfg('api_key');
    }

    public function deliver(string $phone, string $message): SmsResult
    {
        if (! $this->configured()) {
            return $this->failed('ALPHA_SMS_API_KEY not configured');
        }
        try {
            $res = Http::timeout((int) $this->cfg('timeout', 10))->acceptJson()->asForm()->post(rtrim($this->cfg('url'), '/').'/sendsms', array_filter([
                'api_key' => $this->cfg('api_key'), 'msg' => $message, 'to' => Phone::msisdn($phone), 'sender_id' => $this->cfg('sender_id'),
            ]));
        } catch (Throwable $e) {
            return $this->failed('HTTP error: '.$e->getMessage());
        }
        if ($res->successful() && (int) $res->json('error', -1) === 0) {
            $id = $res->json('data.request_id');

            return SmsResult::ok($this->name(), $id !== null ? (string) $id : null);
        }

        return $this->failed('Alpha SMS: '.($res->json('msg') ?? 'HTTP '.$res->status()), $res->body());
    }

    public function balance(): ?float
    {
        if (! $this->configured()) {
            return null;
        }
        try {
            $b = Http::timeout(10)->acceptJson()->get(rtrim($this->cfg('url'), '/').'/user/balance/', ['api_key' => $this->cfg('api_key')])->json('data.balance');

            return is_numeric($b) ? (float) $b : null;
        } catch (Throwable) {
            return null;
        }
    }
}

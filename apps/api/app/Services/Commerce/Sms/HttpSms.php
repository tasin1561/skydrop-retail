<?php

namespace App\Services\Commerce\Sms;

use App\Services\Commerce\Phone;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

/**
 * HTTP driver for a Bangladeshi SMS gateway. Configure with SMS_HTTP_* env keys.
 *  - style "sslwireless": SSL Wireless ISMS v3 (POST JSON {api_token, sid, msisdn, sms, csms_id})
 *  - style "bulksmsbd":   BulkSMSBD (POST form {api_key, type, number, senderid, message})
 * TODO(credentials): confirm field names and success codes with the gateway account you buy; both
 * gateways also need the sender id / masking approved by BTRC before production traffic.
 */
class HttpSms implements SmsDriver
{
    public function send(string $phone, string $message): bool
    {
        $c = config('retail.sms.http');
        if (empty($c['url']) || empty($c['api_token'])) {
            Log::warning('[sms:http] SMS_HTTP_URL / SMS_HTTP_TOKEN not configured; message dropped');

            return false;
        }

        try {
            $req = Http::timeout($c['timeout'] ?? 10)->acceptJson();
            if (($c['style'] ?? 'sslwireless') === 'bulksmsbd') {
                $res = $req->asForm()->post($c['url'], [
                    'api_key' => $c['api_token'], 'type' => 'text', 'number' => Phone::msisdn($phone),
                    'senderid' => $c['sender_id'], 'message' => $message,
                ]);
                // BulkSMSBD answers {"response_code":202,...} on success
                $ok = $res->successful() && (int) $res->json('response_code') === 202;
            } else {
                $res = $req->asJson()->post($c['url'], [
                    'api_token' => $c['api_token'], 'sid' => $c['sender_id'], 'msisdn' => Phone::msisdn($phone),
                    'sms' => $message, 'csms_id' => Str::upper(Str::random(20)),
                ]);
                // SSL Wireless answers {"status":"SUCCESS","status_code":200,...}
                $ok = $res->successful() && strtoupper((string) $res->json('status')) === 'SUCCESS';
            }
            if (! $ok) {
                Log::warning('[sms:http] gateway rejected message', ['status' => $res->status(), 'body' => Str::limit($res->body(), 300)]);
            }

            return $ok;
        } catch (\Throwable $e) {
            Log::error('[sms:http] '.$e->getMessage());

            return false;
        }
    }

    public function name(): string
    {
        return 'http';
    }
}

<?php

namespace App\Services\Commerce\Sms;

use App\Services\Commerce\Phone;
use App\Services\Messaging\SmsSegments;
use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * BulkSMSBD (bulksmsbd.net). Send: GET/POST {url}/smsapi with api_key, type (text|unicode), number (8801…), senderid, message.
 * Success is JSON response_code 202. Balance: {url}/getBalanceApi?api_key=… → { balance }.
 * Needs BULKSMSBD_API_KEY and a BTRC-approved sender id (BULKSMSBD_SENDER_ID).
 */
class BulkSmsBd extends AbstractGateway
{
    /** Documented BulkSMSBD error codes. */
    public const ERRORS = [
        1001 => 'Invalid number', 1002 => 'Sender id not correct or disabled', 1003 => 'Required fields missing',
        1005 => 'Internal error', 1006 => 'Balance validity not available', 1007 => 'Insufficient balance',
        1011 => 'User id not found', 1012 => 'Masking SMS must be sent in Bengali', 1013 => 'Sender id has no gateway for this API key',
        1014 => 'Sender type name not found', 1015 => 'Sender id has no valid gateway', 1016 => 'Sender type price info not found',
        1017 => 'Sender type price not found', 1018 => 'Account disabled', 1019 => 'Sender type price disabled',
        1020 => 'Parent account not found', 1021 => 'Parent active sender type price not found', 1031 => 'Account not verified',
        1032 => 'IP not whitelisted',
    ];

    public function name(): string
    {
        return 'bulksmsbd';
    }

    public function configured(): bool
    {
        return (bool) $this->cfg('api_key') && (bool) $this->cfg('sender_id');
    }

    public function deliver(string $phone, string $message): SmsResult
    {
        if (! $this->configured()) {
            return $this->failed('BULKSMSBD_API_KEY / BULKSMSBD_SENDER_ID not configured');
        }
        try {
            $res = Http::timeout((int) $this->cfg('timeout', 10))->acceptJson()->asForm()->post(rtrim($this->cfg('url'), '/').'/smsapi', [
                'api_key' => $this->cfg('api_key'),
                'type' => SmsSegments::isUnicode($message) ? 'unicode' : 'text',
                'number' => Phone::msisdn($phone),
                'senderid' => $this->cfg('sender_id'),
                'message' => $message,
            ]);
        } catch (Throwable $e) {
            return $this->failed('HTTP error: '.$e->getMessage());
        }
        $json = $res->json();
        $code = (int) ($json['response_code'] ?? 0);
        if ($res->successful() && $code === 202) {
            return SmsResult::ok($this->name(), isset($json['message_id']) ? (string) $json['message_id'] : null);
        }
        $msg = $json['error_message'] ?? (self::ERRORS[$code] ?? null) ?? 'HTTP '.$res->status();

        // Bad number is the recipient's fault: another gateway will not help.
        return $this->failed("BulkSMSBD {$code}: {$msg}", $res->body(), $code !== 1001);
    }

    public function balance(): ?float
    {
        if (! $this->cfg('api_key')) {
            return null;
        }
        try {
            $res = Http::timeout(10)->acceptJson()->get(rtrim($this->cfg('url'), '/').'/getBalanceApi', ['api_key' => $this->cfg('api_key')]);
            $b = $res->json('balance');

            return is_numeric($b) ? (float) $b : null;
        } catch (Throwable) {
            return null;
        }
    }
}

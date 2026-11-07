<?php

namespace App\Services\Commerce\Sms;

use App\Services\Commerce\Phone;
use App\Services\Messaging\MessagingSettings;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * SMS drivers: log (dev), http (legacy generic SSL Wireless / BulkSMSBD), bulksmsbd, sslwireless, alpha.
 * send() walks the failover chain (messaging.sms.failover, else SMS_DRIVER) until one gateway accepts.
 */
class SmsManager
{
    public const DRIVERS = ['log' => 'Log (development)', 'bulksmsbd' => 'BulkSMSBD', 'sslwireless' => 'SSL Wireless', 'alpha' => 'Alpha SMS (sms.net.bd)', 'http' => 'Generic HTTP (legacy)'];

    public function driver(?string $name = null): SmsDriver
    {
        return match ($name ?? config('retail.sms.driver', 'log')) {
            'http' => app(HttpSms::class),
            'bulksmsbd' => app(BulkSmsBd::class),
            'sslwireless' => app(SslWireless::class),
            'alpha' => app(AlphaSms::class),
            default => app(LogSms::class),
        };
    }

    /** Driver used for OTP codes (OTP_DRIVER). */
    public function otp(): SmsDriver
    {
        return $this->driver(config('retail.otp.driver', 'log'));
    }

    /** @return list<string> */
    public function chain(): array
    {
        return MessagingSettings::smsChain();
    }

    /** OTP chain: OTP_DRIVER first, then the other configured gateways (never the log driver in production). */
    public function otpChain(): array
    {
        $first = (string) config('retail.otp.driver', 'log');
        if ($first === 'log') {
            return ['log'];
        }

        return array_values(array_unique(array_merge([$first], array_diff($this->chain(), ['log']))));
    }

    /** Sends through the failover chain. $phone in any BD format. */
    public function send(string $phone, string $message, ?array $chain = null): SmsResult
    {
        $normalized = Phone::normalize($phone);
        if (! $normalized) {
            return SmsResult::fail('none', 'Invalid Bangladeshi mobile number', false);
        }
        $errors = [];
        foreach ($chain ?? $this->chain() as $name) {
            $r = $this->deliverVia($name, $normalized, $message);
            if ($r->ok) {
                return $r;
            }
            $errors[] = $name.': '.$r->error;
            if (! $r->retryable) {
                break;
            }
        }

        return SmsResult::fail('none', implode(' | ', $errors) ?: 'No SMS driver configured', false);
    }

    public function deliverVia(string $name, string $normalized, string $message): SmsResult
    {
        try {
            $driver = $this->driver($name);
            if ($driver instanceof SmsGateway) {
                return $driver->deliver($normalized, $message);
            }

            return $driver->send($normalized, $message) ? SmsResult::ok($driver->name()) : SmsResult::fail($driver->name(), 'Gateway rejected the message');
        } catch (Throwable $e) {
            Log::error('[sms:'.$name.'] '.$e->getMessage());

            return SmsResult::fail($name, $e->getMessage());
        }
    }

    /** @return array<string, ?float> balance per configured gateway in the chain */
    public function balances(): array
    {
        $out = [];
        foreach (array_unique(array_merge($this->chain(), array_keys(self::DRIVERS))) as $name) {
            $d = $this->driver($name);
            if ($d instanceof SmsGateway && $d->configured()) {
                $out[$name] = $d->balance();
            }
        }

        return $out;
    }
}

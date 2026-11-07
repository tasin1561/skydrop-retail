<?php

namespace App\Services\Commerce\Sms;

class SmsManager
{
    public function driver(?string $name = null): SmsDriver
    {
        return match ($name ?? config('retail.sms.driver', 'log')) {
            'http' => app(HttpSms::class),
            default => app(LogSms::class),
        };
    }

    /** Driver used for OTP codes (OTP_DRIVER). */
    public function otp(): SmsDriver
    {
        return $this->driver(config('retail.otp.driver', 'log'));
    }
}

<?php

namespace App\Services\Commerce\Sms;

use App\Services\Commerce\Phone;
use Illuminate\Support\Facades\Log;

// Development driver: writes messages to the log (and keeps them in memory for tests).
class LogSms implements SmsDriver
{
    /** @var array<int, array{phone: string, message: string}> */
    public static array $sent = [];

    public function send(string $phone, string $message): bool
    {
        static::$sent[] = ['phone' => $phone, 'message' => $message];
        Log::info('[sms:log] to '.Phone::mask($phone).': '.$message);

        return true;
    }

    public function name(): string
    {
        return 'log';
    }
}

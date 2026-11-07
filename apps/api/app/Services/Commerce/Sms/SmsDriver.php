<?php

namespace App\Services\Commerce\Sms;

interface SmsDriver
{
    /** @param string $phone normalised +8801XXXXXXXXX */
    public function send(string $phone, string $message): bool;

    public function name(): string;
}

<?php

namespace App\Services\Commerce\Sms;

/** A driver that reports provider message ids / errors and can check its balance. */
interface SmsGateway extends SmsDriver
{
    /** @param string $phone normalised +8801XXXXXXXXX */
    public function deliver(string $phone, string $message): SmsResult;

    /** Remaining balance in BDT (or credits), null when the provider has no balance API / not configured. */
    public function balance(): ?float;

    public function configured(): bool;
}

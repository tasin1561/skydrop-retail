<?php

namespace App\Services\Commerce\Sms;

final class SmsResult
{
    public function __construct(
        public bool $ok,
        public string $driver,
        public ?string $messageId = null,
        public ?string $error = null,
        /** true when retrying another gateway makes sense (network error, 5xx, balance) */
        public bool $retryable = true,
    ) {}

    public static function ok(string $driver, ?string $id = null): self
    {
        return new self(true, $driver, $id);
    }

    public static function fail(string $driver, string $error, bool $retryable = true): self
    {
        return new self(false, $driver, null, $error, $retryable);
    }
}

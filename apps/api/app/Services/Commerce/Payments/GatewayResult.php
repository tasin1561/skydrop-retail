<?php

namespace App\Services\Commerce\Payments;

final class GatewayResult
{
    /** @param string $status paid | failed | pending | refunded */
    public function __construct(
        public string $status,
        public ?string $ref = null,
        public array $payload = [],
        public ?string $message = null,
        public ?float $amount = null,
    ) {}

    public static function paid(?string $ref, array $payload = [], ?float $amount = null): self
    {
        return new self('paid', $ref, $payload, null, $amount);
    }

    public static function failed(string $message, array $payload = []): self
    {
        return new self('failed', null, $payload, $message);
    }

    public static function pending(array $payload = [], ?string $message = null): self
    {
        return new self('pending', null, $payload, $message);
    }
}

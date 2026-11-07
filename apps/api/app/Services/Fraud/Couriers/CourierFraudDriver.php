<?php

namespace App\Services\Fraud\Couriers;

/** A BD courier's "fraud check" / delivery history lookup for a phone number. */
interface CourierFraudDriver
{
    /** Short id used in config (finance.fraud.couriers.<id>) and the courier_checks table. */
    public function id(): string;

    /** Credentials present and switched on. */
    public function enabled(): bool;

    /**
     * @param  string  $phone  local format 01XXXXXXXXX
     * @return array{total: int, delivered: int, cancelled: int, raw: array<string, mixed>}
     *
     * @throws \Throwable on transport / auth errors (the caller records the error and moves on)
     */
    public function check(string $phone): array;
}

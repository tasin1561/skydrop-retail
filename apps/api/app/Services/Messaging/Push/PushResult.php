<?php

namespace App\Services\Messaging\Push;

final class PushResult
{
    /** @param list<string> $ids provider ticket ids */
    public function __construct(public int $sent = 0, public int $failed = 0, public array $ids = [], public array $errors = []) {}

    public function ok(): bool
    {
        return $this->sent > 0;
    }
}

<?php

namespace App\Services\Assistant;

use RuntimeException;

class AnthropicException extends RuntimeException
{
    public function __construct(string $message, public int $status = 0, public ?string $type = null)
    {
        parent::__construct($message, $status);
    }

    /** Overloaded / rate limited / server error: worth retrying with the fallback model. */
    public function retryable(): bool
    {
        return $this->status === 0 || $this->status === 429 || $this->status >= 500;
    }
}

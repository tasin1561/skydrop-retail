<?php

namespace App\Services\Commerce;

use Illuminate\Http\JsonResponse;
use RuntimeException;

// Business-rule failure rendered as { message, code?, ...extra } with an HTTP status.
class CommerceException extends RuntimeException
{
    public function __construct(string $message, public int $status = 422, public array $extra = [], public ?string $errorCode = null)
    {
        parent::__construct($message);
    }

    public static function field(string $field, string $message, int $status = 422): self
    {
        return new self($message, $status, ['errors' => [$field => [$message]]]);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_merge(['message' => $this->getMessage()], $this->errorCode ? ['code' => $this->errorCode] : [], $this->extra), $this->status);
    }
}

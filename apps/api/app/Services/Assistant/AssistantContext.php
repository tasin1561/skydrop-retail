<?php

namespace App\Services\Assistant;

use App\Models\User;

/** Who is asking and from where. Tools only ever see this user's data. */
final class AssistantContext
{
    /** @param list<string> $cartIds */
    public function __construct(
        public ?User $user,
        public string $ipHash,
        public ?string $store = null,
        public ?string $productId = null,
        public array $cartIds = [],
        public string $locale = 'en',
        public string $client = 'web',
    ) {}

    public function guest(): bool
    {
        return $this->user === null;
    }
}

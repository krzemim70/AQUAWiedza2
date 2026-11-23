<?php

namespace App\Domain\Notifications\Data;

final readonly class PushDeliveryResult
{
    public function __construct(
        public bool $success,
        public bool $expired = false,
        public ?int $statusCode = null,
        public string $reason = '',
    ) {}

    public function isSubscriptionExpired(): bool
    {
        return $this->expired || in_array($this->statusCode, [404, 410], true);
    }
}

<?php

namespace App\Domain\Notifications\Data;

final readonly class PushSubscriptionData
{
    public function __construct(
        public string $endpoint,
        public string $p256dh,
        public string $authToken,
        public string $contentEncoding = 'aes128gcm',
    ) {}
}

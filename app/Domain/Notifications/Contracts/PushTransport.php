<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\Data\PushDeliveryResult;
use App\Domain\Notifications\Data\PushPayload;
use App\Domain\Notifications\Models\PushSubscription;

interface PushTransport
{
    public function send(PushSubscription $subscription, PushPayload $payload): PushDeliveryResult;
}

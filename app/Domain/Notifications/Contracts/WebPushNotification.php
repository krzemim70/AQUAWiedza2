<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\Data\PushPayload;
use App\Models\User;

interface WebPushNotification
{
    public function toWebPush(User $notifiable): ?PushPayload;
}

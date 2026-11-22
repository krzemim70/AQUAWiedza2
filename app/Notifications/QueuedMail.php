<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Support\Facades\Log;
use Throwable;

trait QueuedMail
{
    use Queueable;

    public int $tries = 3;

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Wysyłka powiadomienia zakończyła się błędem.', [
            'notification' => static::class,
            'notification_id' => $this->id,
            'error_type' => $exception::class,
        ]);
    }
}

<?php

namespace App\Domain\IdentityAccess\Notifications;

use App\Models\User;
use App\Notifications\QueuedMail;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\URL;

class VerifyEmailNotice extends Notification implements ShouldQueueAfterCommit
{
    use QueuedMail;

    public function __construct(private int $lifetimeSeconds) {}

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return ! $notifiable->hasVerifiedEmail();
    }

    public function toMail(User $notifiable): MailMessage
    {
        $expiresAt = now()->addSeconds($this->lifetimeSeconds);
        $url = URL::temporarySignedRoute('verification.verify', $expiresAt, [
            'id' => $notifiable->getKey(),
            'hash' => hash('sha256', $notifiable->getEmailForVerification()),
        ]);

        return (new MailMessage)
            ->subject(__('mail.verify.subject'))
            ->line(__('mail.verify.intro'))
            ->line(__('mail.verify.expires', ['date' => $expiresAt->timezone(config('app.display_timezone'))->format('d.m.Y, H:i T')]))
            ->action(__('mail.verify.action'), $url)
            ->line(__('mail.verify.ignore'));
    }
}

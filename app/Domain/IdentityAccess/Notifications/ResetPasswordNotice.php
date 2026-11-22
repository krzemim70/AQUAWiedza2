<?php

namespace App\Domain\IdentityAccess\Notifications;

use App\Models\User;
use App\Notifications\QueuedMail;
use Illuminate\Contracts\Queue\ShouldBeEncrypted;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Password;
use SensitiveParameter;

class ResetPasswordNotice extends Notification implements ShouldBeEncrypted, ShouldQueueAfterCommit
{
    use QueuedMail;

    public function __construct(#[SensitiveParameter] public string $token) {}

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return Password::tokenExists($notifiable, $this->token);
    }

    public function toMail(User $notifiable): MailMessage
    {
        $broker = config('auth.defaults.passwords');
        $minutes = (int) config("auth.passwords.{$broker}.expire");
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);

        return (new MailMessage)
            ->subject(__('mail.reset.subject'))
            ->line(__('mail.reset.intro'))
            ->line(trans_choice('mail.reset.expires', $minutes))
            ->action(__('mail.reset.action'), $url)
            ->line(__('mail.reset.ignore'));
    }
}

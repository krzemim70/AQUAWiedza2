<?php

namespace App\Domain\Notifications;

use App\Domain\Notifications\Contracts\Preferences;
use App\Models\User;
use App\Notifications\QueuedMail;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;

abstract class PreferenceMail extends Notification implements ShouldQueueAfterCommit
{
    use QueuedMail;

    private ?int $queueRecipient = null;

    abstract protected function topic(): Topic;

    /** Wywołać po zatwierdzeniu transakcji. Preferencja zostanie sprawdzona ponownie przed wysyłką. */
    public function queueFor(User $user): bool
    {
        if (! $this->allowsEmail($user)) {
            return false;
        }

        $this->queueRecipient = $user->id;
        $user->notify($this);

        return true;
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User && ($this->queueRecipient === $notifiable->id || $this->allowsEmail($notifiable))
            ? ['mail'] : [];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        return $channel === 'mail' && $this->allowsEmail($notifiable);
    }

    private function allowsEmail(User $user): bool
    {
        return app(Preferences::class)->allowsEmail($user->id, $this->topic());
    }
}

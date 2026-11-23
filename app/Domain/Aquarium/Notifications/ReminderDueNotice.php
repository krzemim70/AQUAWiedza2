<?php

namespace App\Domain\Aquarium\Notifications;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Notifications\Contracts\Preferences;
use App\Domain\Notifications\Contracts\WebPushNotification;
use App\Domain\Notifications\Data\PushPayload;
use App\Domain\Notifications\Topic;
use App\Domain\Notifications\WebPushChannel;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueueAfterCommit;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;
use Throwable;

class ReminderDueNotice extends Notification implements ShouldQueueAfterCommit, WebPushNotification
{
    use Queueable;

    public int $tries = 3;

    /**
     * @param  list<array{tank_id: int, tank_name: string, title: string}>  $items
     */
    public function __construct(public array $items) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [60, 300];
    }

    /** @return list<string> */
    public function via(object $notifiable): array
    {
        return $notifiable instanceof User ? [WebPushChannel::class] : [];
    }

    public function shouldSend(User $notifiable, string $channel): bool
    {
        if ($channel !== WebPushChannel::class) {
            return false;
        }

        $config = (array) app(Settings::class)->get(SettingKey::PushNotifications);
        if (! ($config['enabled'] ?? false)) {
            return false;
        }

        $access = app(ResolveAccess::class);

        return $access->handle($notifiable, Feature::PushNotifications)->allowed
            && $access->handle($notifiable, Feature::MeasurementHistory)->allowed
            && app(Preferences::class)->allowsPush($notifiable->id, Topic::Reminders);
    }

    public function toWebPush(User $notifiable): ?PushPayload
    {
        if ($this->items === []) {
            return null;
        }

        if (count($this->items) === 1) {
            $item = $this->items[0];

            return new PushPayload(
                title: 'Przypomnienie — '.$item['tank_name'],
                body: $item['title'],
                url: route('aquarium.tank', ['tank' => $item['tank_id'], 'karta' => 'przypomnienia'], false),
                tag: 'reminder-'.$item['tank_id'],
            );
        }

        $count = count($this->items);
        $summary = implode(', ', array_map(
            fn (array $item): string => $item['tank_name'].': '.$item['title'],
            array_slice($this->items, 0, 3),
        ));
        if ($count > 3) {
            $summary .= '…';
        }

        return new PushPayload(
            title: 'Przypomnienia na dziś ('.$count.')',
            body: $summary,
            url: route('aquarium.index', [], false),
            tag: 'reminders-today',
        );
    }

    public function failed(Throwable $exception): void
    {
        Log::error('Wysyłka powiadomienia push o przypomnieniu zakończyła się błędem.', [
            'notification' => static::class,
            'notification_id' => $this->id,
            'error_type' => $exception::class,
        ]);
    }
}

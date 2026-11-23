<?php

namespace App\Domain\Notifications;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Notifications\Contracts\PushTransport;
use App\Domain\Notifications\Contracts\WebPushNotification;
use App\Domain\Notifications\Models\PushSubscription;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Log;

class WebPushChannel
{
    public function __construct(
        private PushTransport $transport,
        private ResolveAccess $access,
        private Settings $settings,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof WebPushNotification) {
            return;
        }

        $config = (array) $this->settings->get(SettingKey::PushNotifications);
        if (! ($config['enabled'] ?? false)) {
            return;
        }

        if (! $this->access->handle($notifiable, Feature::PushNotifications)->allowed) {
            return;
        }

        $payload = $notification->toWebPush($notifiable);
        if ($payload === null) {
            return;
        }

        $subscriptions = PushSubscription::where('user_id', $notifiable->id)->orderBy('id')->get();
        foreach ($subscriptions as $subscription) {
            $result = $this->transport->send($subscription, $payload);

            if ($result->success) {
                $subscription->update(['last_used_at' => now()]);

                continue;
            }

            if ($result->isSubscriptionExpired()) {
                $subscription->delete();

                continue;
            }

            Log::warning('Wysyłka powiadomienia push nie powiodła się.', [
                'subscription_id' => $subscription->id,
                'status_code' => $result->statusCode,
                'reason' => $result->reason,
            ]);
        }
    }
}

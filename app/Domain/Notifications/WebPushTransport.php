<?php

namespace App\Domain\Notifications;

use App\Domain\Notifications\Contracts\PushTransport;
use App\Domain\Notifications\Data\PushDeliveryResult;
use App\Domain\Notifications\Data\PushPayload;
use App\Domain\Notifications\Models\PushSubscription;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

class WebPushTransport implements PushTransport
{
    public function send(PushSubscription $subscription, PushPayload $payload): PushDeliveryResult
    {
        $vapid = $this->vapid();
        if ($vapid === null) {
            return new PushDeliveryResult(false, false, null, 'Brak konfiguracji kluczy VAPID.');
        }

        $webPush = new WebPush(['VAPID' => $vapid]);
        $target = Subscription::create([
            'endpoint' => $subscription->endpoint,
            'publicKey' => $subscription->p256dh,
            'authToken' => $subscription->auth_token,
            'contentEncoding' => $subscription->content_encoding,
        ]);

        $report = $webPush->sendOneNotification($target, $payload->toJson());
        $statusCode = $report->getResponse()?->getStatusCode();

        return new PushDeliveryResult(
            success: $report->isSuccess(),
            expired: $report->isSubscriptionExpired(),
            statusCode: $statusCode,
            reason: $report->getReason(),
        );
    }

    /** @return array{subject: string, publicKey: string, privateKey: string}|null */
    private function vapid(): ?array
    {
        $subject = trim((string) config('services.webpush.subject', ''));
        $publicKey = trim((string) config('services.webpush.public_key', ''));
        $privateKey = trim((string) config('services.webpush.private_key', ''));

        if ($subject === '' || $publicKey === '' || $privateKey === '') {
            return null;
        }

        return [
            'subject' => $subject,
            'publicKey' => $publicKey,
            'privateKey' => $privateKey,
        ];
    }
}

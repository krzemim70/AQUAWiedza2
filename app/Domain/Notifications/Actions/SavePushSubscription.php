<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Notifications\Data\PushSubscriptionData;
use App\Domain\Notifications\Models\PushSubscription;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class SavePushSubscription
{
    public function __construct(private ResolveAccess $access, private Settings $settings) {}

    public function handle(User $actor, PushSubscriptionData $data): PushSubscription
    {
        $decision = $this->access->handle($actor, Feature::PushNotifications);
        $decision->allowed || throw new AuthorizationException($decision->message);

        $config = (array) $this->settings->get(SettingKey::PushNotifications);
        if (! ($config['enabled'] ?? false)) {
            throw ValidationException::withMessages(['push' => 'Powiadomienia push są obecnie wyłączone.']);
        }

        $validated = Validator::make([
            'endpoint' => trim($data->endpoint),
            'p256dh' => trim($data->p256dh),
            'auth_token' => trim($data->authToken),
            'content_encoding' => trim($data->contentEncoding) !== '' ? trim($data->contentEncoding) : 'aes128gcm',
        ], [
            'endpoint' => ['required', 'string', 'max:2048', 'url:https'],
            'p256dh' => ['required', 'string', 'max:255'],
            'auth_token' => ['required', 'string', 'max:255'],
            'content_encoding' => ['required', 'string', 'in:aes128gcm,aesgcm'],
        ], [
            'endpoint.required' => 'Brak adresu subskrypcji push.',
            'endpoint.url' => 'Adres subskrypcji push musi używać protokołu HTTPS.',
            'p256dh.required' => 'Brak klucza szyfrowania subskrypcji.',
            'auth_token.required' => 'Brak tokenu autoryzacji subskrypcji.',
            'content_encoding.in' => 'Nieobsługiwane kodowanie powiadomień push.',
        ])->validate();

        $hash = PushSubscription::hashEndpoint($validated['endpoint']);

        return DB::transaction(fn () => PushSubscription::updateOrCreate(
            ['endpoint_hash' => $hash],
            [
                'user_id' => $actor->id,
                'endpoint' => $validated['endpoint'],
                'p256dh' => $validated['p256dh'],
                'auth_token' => $validated['auth_token'],
                'content_encoding' => $validated['content_encoding'],
            ],
        ));
    }
}

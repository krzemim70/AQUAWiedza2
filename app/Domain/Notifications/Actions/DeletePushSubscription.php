<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\IdentityAccess\Actions\ResolveAccess;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Notifications\Models\PushSubscription;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

class DeletePushSubscription
{
    public function __construct(private ResolveAccess $access) {}

    public function handle(User $actor, ?string $endpoint = null): int
    {
        $decision = $this->access->handle($actor, Feature::PushNotifications);
        $decision->allowed || throw new AuthorizationException($decision->message);

        $query = PushSubscription::where('user_id', $actor->id);
        $trimmed = $endpoint !== null ? trim($endpoint) : '';
        if ($trimmed !== '') {
            $query->where('endpoint_hash', PushSubscription::hashEndpoint($trimmed));
        }

        return (int) $query->delete();
    }
}

<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\Models\PushSubscription;

class PushSubscriptions
{
    public function hasAnyFor(int $userId): bool
    {
        return PushSubscription::where('user_id', $userId)->exists();
    }

    public function countFor(int $userId): int
    {
        return PushSubscription::where('user_id', $userId)->count();
    }

    /**
     * @param  list<int>  $userIds
     * @return list<int>
     */
    public function userIdsWithSubscriptions(array $userIds): array
    {
        if ($userIds === []) {
            return [];
        }

        return array_values(array_map(
            'intval',
            PushSubscription::whereIn('user_id', $userIds)->distinct()->pluck('user_id')->all(),
        ));
    }
}

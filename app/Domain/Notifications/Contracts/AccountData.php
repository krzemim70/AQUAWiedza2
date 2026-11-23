<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\Models\PushSubscription;
use Illuminate\Support\Facades\DB;

class AccountData
{
    /** @return array<string, bool> */
    public function exportFor(int $userId): array
    {
        return DB::table('notification_preferences')->where('user_id', $userId)->orderBy('topic')
            ->pluck('email', 'topic')->map(fn ($email) => (bool) $email)->all();
    }

    /** @return array<string, bool> */
    public function pushPreferencesFor(int $userId): array
    {
        return DB::table('notification_preferences')->where('user_id', $userId)->orderBy('topic')
            ->pluck('push', 'topic')->map(fn ($push) => (bool) $push)->all();
    }

    /** @return list<array<string, mixed>> */
    public function pushSubscriptionsFor(int $userId): array
    {
        return array_values(PushSubscription::where('user_id', $userId)
            ->orderBy('id')
            ->get(['endpoint', 'content_encoding', 'last_used_at', 'created_at', 'updated_at'])
            ->toArray());
    }

    /** Wywołać w transakcji usuwającej konto. */
    public function eraseFor(int $userId): void
    {
        PushSubscription::where('user_id', $userId)->delete();
        DB::table('notification_preferences')->where('user_id', $userId)->delete();
    }
}

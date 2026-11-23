<?php

namespace App\Domain\Notifications\Contracts;

use Illuminate\Support\Facades\DB;

class AccountData
{
    /** @return array<string, bool> */
    public function exportFor(int $userId): array
    {
        return DB::table('notification_preferences')->where('user_id', $userId)->orderBy('topic')
            ->pluck('email', 'topic')->map(fn ($email) => (bool) $email)->all();
    }
}

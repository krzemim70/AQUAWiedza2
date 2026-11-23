<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveNotificationPreference
{
    public function handle(User $user, Topic $topic, ?bool $email = null, ?bool $push = null): void
    {
        $update = [];
        if ($email !== null) {
            $update[] = 'email';
        }
        if ($push !== null) {
            $update[] = 'push';
        }
        if ($update === []) {
            return;
        }

        DB::table('notification_preferences')->upsert([
            [
                'user_id' => $user->id,
                'topic' => $topic->value,
                'email' => $email ?? true,
                'push' => $push ?? false,
            ],
        ], ['user_id', 'topic'], $update);
    }
}

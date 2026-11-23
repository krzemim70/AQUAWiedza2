<?php

namespace App\Domain\Notifications\Actions;

use App\Domain\Notifications\Topic;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class SaveNotificationPreference
{
    public function handle(User $user, Topic $topic, bool $email): void
    {
        DB::table('notification_preferences')->upsert([
            ['user_id' => $user->id, 'topic' => $topic->value, 'email' => $email],
        ], ['user_id', 'topic'], ['email']);
    }
}

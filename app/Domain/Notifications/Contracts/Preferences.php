<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\Topic;
use Illuminate\Support\Facades\DB;

class Preferences
{
    public function allowsEmail(int $userId, Topic $topic): bool
    {
        $email = DB::table('notification_preferences')->where('user_id', $userId)->where('topic', $topic->value)->value('email');

        return $email === null || (bool) $email;
    }
}

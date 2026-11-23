<?php

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\Topic;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use Illuminate\Support\Facades\DB;

class Preferences
{
    public function __construct(private Settings $settings) {}

    public function allowsEmail(int $userId, Topic $topic): bool
    {
        $email = DB::table('notification_preferences')->where('user_id', $userId)->where('topic', $topic->value)->value('email');

        return $email === null || (bool) $email;
    }

    public function allowsPush(int $userId, Topic $topic): bool
    {
        $push = DB::table('notification_preferences')->where('user_id', $userId)->where('topic', $topic->value)->value('push');
        if ($push !== null) {
            return (bool) $push;
        }

        if ($topic === Topic::LowStock) {
            return (bool) (((array) $this->settings->get(SettingKey::PushNotifications))['low_stock_default'] ?? false);
        }

        return false;
    }
}

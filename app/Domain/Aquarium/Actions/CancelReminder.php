<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CancelReminder
{
    public function __construct(private Access $access) {}

    public function handle(User $actor, int $reminderId): void
    {
        $this->access->reminder($actor, $reminderId);

        DB::transaction(function () use ($reminderId) {
            $reminder = Reminder::lockForUpdate()->findOrFail($reminderId);
            if ($reminder->state === Reminder::ACTIVE) {
                $reminder->update(['state' => Reminder::CANCELLED, 'version' => $reminder->version + 1]);
            }
        });
    }
}

<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\ReminderData;
use App\Domain\Aquarium\Models\Reminder;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class CreateReminder
{
    public function __construct(private Access $access) {}

    /** Zapisuje przypomnienie razem z pierwszym wystąpieniem w terminie startu. */
    public function handle(User $actor, int $tankId, ReminderData $data): Reminder
    {
        $tank = $this->access->tank($actor, $tankId, Feature::MeasurementHistory);
        $a = $data->attributes();

        return DB::transaction(function () use ($tank, $a) {
            $reminder = $tank->reminders()->create([
                'type' => $a['type'], 'label' => $a['label'], 'starts_on' => $a['due_on'], 'interval_days' => $a['interval_days'],
            ]);
            $reminder->occurrences()->create(['due_on' => $a['due_on']]);

            return $reminder;
        });
    }
}

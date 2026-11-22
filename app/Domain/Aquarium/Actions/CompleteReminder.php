<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Models\Reminder;
use App\Domain\Aquarium\Models\ReminderOccurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class CompleteReminder
{
    public function __construct(private Access $access) {}

    /**
     * Wykonuje wskazane wystąpienie. Powtórzenie (np. podwójne wysłanie formularza) zwraca zapisany efekt:
     * cykliczne tworzy dokładnie jedno następne wystąpienie (termin + interwał), jednorazowe zamyka przypomnienie.
     */
    public function handle(User $actor, int $occurrenceId): ReminderOccurrence
    {
        $reminderId = $this->access->occurrence($actor, $occurrenceId)->reminder_id;

        return DB::transaction(function () use ($actor, $reminderId, $occurrenceId) {
            $reminder = Reminder::lockForUpdate()->findOrFail($reminderId);
            $occurrence = ReminderOccurrence::lockForUpdate()->findOrFail($occurrenceId);

            if ($occurrence->state === ReminderOccurrence::DONE) {
                return $occurrence;
            }
            if ($reminder->state !== Reminder::ACTIVE) {
                throw ValidationException::withMessages(['form' => 'To przypomnienie zostało anulowane.']);
            }

            $occurrence->update(['state' => ReminderOccurrence::DONE, 'completed_at' => now(), 'completed_by' => $actor->id]);

            if ($reminder->interval_days === null) {
                $reminder->update(['state' => Reminder::COMPLETED, 'version' => $reminder->version + 1]);
            } else {
                $reminder->occurrences()->firstOrCreate(['due_on' => $occurrence->due_on->copy()->addDays($reminder->interval_days)->toDateString()]);
            }

            return $occurrence;
        });
    }
}

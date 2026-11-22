<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\ReminderData;
use App\Domain\Aquarium\Models\Reminder;
use App\Domain\Aquarium\Models\ReminderOccurrence;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class UpdateReminder
{
    public function __construct(private Access $access) {}

    /**
     * Zmienia harmonogram oczekujący: rodzaj, nazwę, interwał i termin najbliższego wystąpienia.
     * Wykonane wystąpienia zostają jako historia. Wersja z formularza musi zgadzać się z zapisaną.
     */
    public function handle(User $actor, int $reminderId, ReminderData $data): Reminder
    {
        $this->access->reminder($actor, $reminderId);
        $a = $data->attributes();

        return DB::transaction(function () use ($reminderId, $a, $data) {
            $reminder = Reminder::lockForUpdate()->findOrFail($reminderId);
            if ($reminder->state !== Reminder::ACTIVE) {
                throw ValidationException::withMessages(['form' => 'To przypomnienie jest zakończone lub anulowane i nie można go edytować.']);
            }
            if ($data->version !== $reminder->version) {
                throw ValidationException::withMessages(['form' => 'Przypomnienie zostało w międzyczasie zmienione. Odśwież stronę i spróbuj ponownie.']);
            }

            $pending = $reminder->occurrences()->where('state', ReminderOccurrence::PENDING)->lockForUpdate()->first();
            if ($pending !== null && $pending->due_on->toDateString() !== $a['due_on']) {
                if ($reminder->occurrences()->whereDate('due_on', $a['due_on'])->exists()) {
                    throw ValidationException::withMessages(['due_on' => 'Na ten dzień to przypomnienie ma już wystąpienie.']);
                }
                $pending->update(['due_on' => $a['due_on']]);
            }

            $reminder->update([
                'type' => $a['type'], 'label' => $a['label'], 'interval_days' => $a['interval_days'], 'version' => $reminder->version + 1,
            ]);

            return $reminder;
        });
    }
}

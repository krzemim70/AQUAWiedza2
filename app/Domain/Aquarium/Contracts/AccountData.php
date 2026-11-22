<?php

namespace App\Domain\Aquarium\Contracts;

use App\Domain\Aquarium\Models\Tank;
use App\Models\User;

class AccountData
{
    /** @return list<array<string, mixed>> */
    public function tanksFor(User $user): array
    {
        return Tank::ownedBy($user)->orderBy('id')->with([
            'livestock', 'measurements', 'journalEntries', 'reminders.occurrences' => fn ($query) => $query->select([
                'id', 'reminder_id', 'due_on', 'state', 'completed_at',
            ]),
        ])->get()->toArray();
    }
}

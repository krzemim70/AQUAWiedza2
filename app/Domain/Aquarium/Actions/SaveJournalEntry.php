<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\JournalEntryData;
use App\Domain\Aquarium\Models\JournalEntry;
use App\Domain\Aquarium\Validation;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;

class SaveJournalEntry
{
    public function __construct(private Access $access) {}

    public function handle(User $actor, int $tankId, JournalEntryData $data): JournalEntry
    {
        $tank = $this->access->tank($actor, $tankId, Feature::MeasurementHistory);

        $v = Validation::validate(
            ['entry_date' => $data->entryDate, 'content' => trim($data->content)],
            ['entry_date' => ['required', 'date_format:Y-m-d'], 'content' => ['required', 'string', 'max:5000']],
            ['entry_date' => 'Data', 'content' => 'Treść'],
        );

        return $tank->journalEntries()->create($v);
    }
}

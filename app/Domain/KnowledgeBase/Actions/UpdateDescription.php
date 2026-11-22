<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Models\Description;
use App\Domain\KnowledgeBase\Models\Record;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/** Ręczna edycja opisu rekordu; kolejne importy jej nie nadpisują. */
class UpdateDescription
{
    public function handle(User $actor, Record $record, string $body, ?string $sourceRef): Description
    {
        Gate::forUser($actor)->authorize('manage-knowledge');

        if (trim($body) === '') {
            throw ValidationException::withMessages(['body' => 'Opis nie może być pusty.']);
        }

        return DB::transaction(function () use ($actor, $record, $body, $sourceRef) {
            $description = Description::updateOrCreate(
                ['record_id' => $record->id],
                ['body' => trim($body), 'source_ref' => $sourceRef, 'edited_by' => $actor->id, 'edited_at' => now()],
            );
            activity()->performedOn($record)->causedBy($actor)->log('catalog description edited');

            return $description;
        });
    }
}

<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Models\Photo;
use App\Domain\KnowledgeBase\Models\Record;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Validator;

/** Dodanie lub zmiana metadanych zdjęcia rekordu (bez plików). Weryfikacja zapisuje datę. */
class SavePhoto
{
    /** @param array{url?: ?string, file_path?: ?string, source_url?: ?string, author?: ?string, licence?: ?string, verified?: bool} $data */
    public function handle(User $actor, Record $record, array $data, ?Photo $photo = null): Photo
    {
        Gate::forUser($actor)->authorize('manage-knowledge');

        $data = Validator::make($data, [
            'url' => ['nullable', 'string', 'max:512', 'required_without:file_path'],
            'file_path' => ['nullable', 'string', 'max:512', 'required_without:url'],
            'source_url' => ['nullable', 'string', 'max:512'],
            'author' => ['nullable', 'string', 'max:255'],
            'licence' => ['nullable', 'string', 'max:255'],
            'verified' => ['boolean'],
        ], ['required_without' => 'Podaj adres URL albo ścieżkę pliku.'])->validate();

        return DB::transaction(function () use ($actor, $record, $data, $photo) {
            $photo ??= new Photo(['record_id' => $record->id]);
            $verified = (bool) ($data['verified'] ?? false);
            $verifiedAt = $verified ? ($photo->verified_at ?? now()) : null;
            $photo->fill($data)->fill(['verified' => $verified, 'verified_at' => $verifiedAt])->save();
            activity()->performedOn($record)->causedBy($actor)->withProperties(['photo' => $photo->id])->log('catalog photo saved');

            return $photo;
        });
    }
}

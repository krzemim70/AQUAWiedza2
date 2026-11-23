<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Data\SaveRecordSpecData;
use App\Domain\KnowledgeBase\Models\Record;
use App\Domain\KnowledgeBase\Models\RecordSpec;
use App\Domain\KnowledgeBase\Models\RecordSpecStatus;
use App\Domain\KnowledgeBase\RecordSpecValidator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Zapisuje lub aktualizuje pola strukturalne rekordu jako szkic (wymaga ponownej weryfikacji). */
class SaveRecordSpec
{
    public function __construct(private RecordSpecValidator $validator) {}

    public function handle(User $actor, SaveRecordSpecData $data): RecordSpec
    {
        Gate::forUser($actor)->authorize('create', RecordSpec::class);

        $record = Record::query()->findOrFail($data->recordId);
        $existing = RecordSpec::query()->where('record_id', $record->id)->first();
        if ($existing !== null) {
            Gate::forUser($actor)->authorize('update', $existing);
        }

        $normalized = $this->validator->validateAndNormalize(
            $record,
            $data->sourceKind,
            $data->sourceRef,
            $data->evidenceQuote,
            $data->ean,
            $data->attributes,
        );

        return DB::transaction(function () use ($actor, $record, $data, $normalized): RecordSpec {
            $spec = RecordSpec::query()->lockForUpdate()->where('record_id', $record->id)->first()
                ?? new RecordSpec(['record_id' => $record->id]);

            $isNew = ! $spec->exists;
            $spec->fill([
                'status' => RecordSpecStatus::Draft,
                'source_kind' => $data->sourceKind,
                'source_ref' => $normalized['source_ref'],
                'evidence_quote' => $normalized['evidence_quote'],
                'ean' => $normalized['ean'],
                'attributes' => $normalized['attributes'],
                'verified_by' => null,
                'verified_at' => null,
                'disabled_reason' => null,
                'disabled_at' => null,
            ]);
            $spec->save();

            activity('catalog_record_specs')
                ->performedOn($spec)
                ->causedBy($actor)
                ->event($isNew ? 'created' : 'updated')
                ->withProperties([
                    'record' => $record->legacy_id,
                    'area' => $record->area->value,
                    'source_kind' => $data->sourceKind->value,
                ])
                ->log($isNew ? 'Utworzono szkic specyfikacji rekordu.' : 'Zaktualizowano szkic specyfikacji rekordu.');

            return $spec->refresh();
        });
    }
}

<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Models\RecordSpec;
use App\Domain\KnowledgeBase\Models\RecordSpecStatus;
use App\Domain\KnowledgeBase\RecordSpecValidator;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Weryfikuje specyfikację rekordu po ponownym sprawdzeniu reguł źródeł, atrybutów i cytatu. */
class VerifyRecordSpec
{
    public function __construct(private RecordSpecValidator $validator) {}

    public function handle(User $actor, RecordSpec $spec): RecordSpec
    {
        Gate::forUser($actor)->authorize('verify', $spec);

        return DB::transaction(function () use ($actor, $spec): RecordSpec {
            $spec = RecordSpec::query()->lockForUpdate()->with('record')->findOrFail($spec->id);

            $this->validator->validateAndNormalize(
                $spec->record,
                $spec->source_kind,
                $spec->source_ref,
                $spec->evidence_quote,
                $spec->ean,
                $spec->attributes,
            );

            $spec->fill([
                'status' => RecordSpecStatus::Verified,
                'verified_by' => $actor->id,
                'verified_at' => now(),
                'disabled_reason' => null,
                'disabled_at' => null,
            ]);
            $spec->save();

            activity('catalog_record_specs')
                ->performedOn($spec)
                ->causedBy($actor)
                ->event('verified')
                ->withProperties([
                    'record' => $spec->record->legacy_id,
                    'area' => $spec->record->area->value,
                    'source_kind' => $spec->source_kind->value,
                ])
                ->log('Zweryfikowano specyfikację rekordu.');

            return $spec->refresh();
        });
    }
}

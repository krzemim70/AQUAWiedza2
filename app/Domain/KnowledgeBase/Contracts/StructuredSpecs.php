<?php

namespace App\Domain\KnowledgeBase\Contracts;

use App\Domain\KnowledgeBase\Data\StructuredSpec;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\RecordSpec;

/** Odczyt zweryfikowanych specyfikacji strukturalnych dla innych modułów. Zwraca wyłącznie wpisy `verified`. */
class StructuredSpecs
{
    public function find(Area $area, string $id): ?StructuredSpec
    {
        $spec = RecordSpec::query()
            ->verified()
            ->whereHas('record', fn ($q) => $q->where('area', $area->value)->where('legacy_id', $id))
            ->with('record')
            ->first();

        return $spec === null ? null : $this->dto($spec);
    }

    /**
     * Zweryfikowane specyfikacje dla wskazanych rekordów obszaru (klucz: legacy_id).
     *
     * @param  list<string>  $ids
     * @return array<string, StructuredSpec>
     */
    public function forRecords(Area $area, array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return RecordSpec::query()
            ->verified()
            ->whereHas('record', fn ($q) => $q->where('area', $area->value)->whereIn('legacy_id', $ids))
            ->with('record')
            ->get()
            ->mapWithKeys(fn (RecordSpec $spec) => [$spec->record->legacy_id => $this->dto($spec)])
            ->all();
    }

    /**
     * Wszystkie zweryfikowane specyfikacje danego obszaru (klucz: legacy_id).
     *
     * @return array<string, StructuredSpec>
     */
    public function forArea(Area $area): array
    {
        return RecordSpec::query()
            ->verified()
            ->whereHas('record', fn ($q) => $q->where('area', $area->value))
            ->with('record')
            ->get()
            ->mapWithKeys(fn (RecordSpec $spec) => [$spec->record->legacy_id => $this->dto($spec)])
            ->all();
    }

    private function dto(RecordSpec $spec): StructuredSpec
    {
        return new StructuredSpec(
            area: $spec->record->area,
            id: $spec->record->legacy_id,
            sourceKind: $spec->source_kind,
            sourceRef: $spec->source_ref,
            evidenceQuote: $spec->evidence_quote,
            ean: $spec->ean,
            attributes: $spec->attributes,
            verifiedAt: $spec->verified_at,
        );
    }
}

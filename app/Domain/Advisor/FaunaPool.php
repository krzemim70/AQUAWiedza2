<?php

namespace App\Domain\Advisor;

use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\Temperament;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\Advisor\Engine\Zone;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;

/** Aktywna fauna z bazy wiedzy w postaci rekordów silnika. */
class FaunaPool
{
    public function __construct(private Catalog $catalog) {}

    /**
     * @param  list<string>|null  $ids
     * @return list<FaunaRecord>
     */
    public function records(?int $batch = null, ?array $ids = null, bool $withDescriptions = false): array
    {
        return array_map($this->record(...), $this->catalog->snapshot(Area::Fauna, $batch, $ids, $withDescriptions));
    }

    private function record(CatalogEntry $e): FaunaRecord
    {
        $ph = array_map(PhBand::fromLabel(...), $e->values['pH'] ?? []);

        return new FaunaRecord(
            $e->id,
            $e->name,
            (string) $e->nameLatin,
            trim($e->fields['Typ'] ?? ''),
            FishBand::fromLabel($e->fields['Wielkość'] ?? null),
            TemperatureBand::fromLabel($e->fields['Temperatura'] ?? null),
            array_values(array_filter($ph)),
            array_values(array_filter(array_map(TankBand::fromLabel(...), $e->values['WielkośćAkwarium'] ?? []))),
            in_array('Wszystkie', $e->values['WielkośćAkwarium'] ?? [], true),
            $e->values['TypAkwarium'] ?? [],
            Temperament::fromLabel($e->fields['Temperament'] ?? null),
            Zone::fromLabel($e->fields['Strefa'] ?? null),
            in_array(null, $ph, true),
            $e->description,
        );
    }

    /**
     * Typy akwarium występujące w katalogu fauny (do listy wyboru).
     *
     * @return list<string>
     */
    public function tankTypes(): array
    {
        $types = array_merge(...array_map(fn (FaunaRecord $r) => $r->tankTypes, $this->records()));

        return array_values(array_unique(array_diff($types, ['Wszystkie'])));
    }
}

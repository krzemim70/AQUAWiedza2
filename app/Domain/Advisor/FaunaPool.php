<?php

namespace App\Domain\Advisor;

use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;

/** Aktywna fauna z bazy wiedzy w postaci rekordów silnika. */
class FaunaPool
{
    public function __construct(private Catalog $catalog) {}

    /** @return list<FaunaRecord> */
    public function records(): array
    {
        return array_map(fn (CatalogEntry $e) => new FaunaRecord(
            $e->id,
            $e->name,
            (string) $e->nameLatin,
            trim($e->fields['Typ'] ?? ''),
            FishBand::fromLabel($e->fields['Wielkość'] ?? null),
            TemperatureBand::fromLabel($e->fields['Temperatura'] ?? null),
            array_values(array_filter(array_map(PhBand::fromLabel(...), $e->values['pH'] ?? []))),
            array_values(array_filter(array_map(TankBand::fromLabel(...), $e->values['WielkośćAkwarium'] ?? []))),
            in_array('Wszystkie', $e->values['WielkośćAkwarium'] ?? [], true),
            $e->values['TypAkwarium'] ?? [],
        ), $this->catalog->snapshot(Area::Fauna));
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

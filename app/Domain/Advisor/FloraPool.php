<?php

namespace App\Domain\Advisor;

use App\Domain\Advisor\Engine\Co2Need;
use App\Domain\Advisor\Engine\FloraRecord;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;

/** Aktywna flora z bazy wiedzy w postaci rekordów silnika. */
class FloraPool
{
    public function __construct(private Catalog $catalog) {}

    /** @return list<FloraRecord> */
    public function records(): array
    {
        return array_map(fn (CatalogEntry $e) => new FloraRecord(
            $e->id,
            $e->name,
            (string) $e->nameLatin,
            Co2Need::fromLabel($e->fields['CO2'] ?? null),
            array_values(array_filter(array_map(TankBand::fromLabel(...), $e->values['WielkośćAkwarium'] ?? []))),
            in_array('Wszystkie', $e->values['WielkośćAkwarium'] ?? [], true),
            $e->values['TypAkwarium'] ?? [],
        ), $this->catalog->snapshot(Area::Flora));
    }
}

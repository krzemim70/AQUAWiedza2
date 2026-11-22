<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Advisor\Contracts\Compatibility;
use App\Domain\Advisor\Engine\Compatibility\CompatibilityReport;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\Compatibility\TankContext;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Models\User;

/** Zgodność obsady zbiornika według bazy wiedzy: cała obsada (karta) albo kandydat do dodania (ostrzeżenie). */
class CheckLivestockFit
{
    public function __construct(private Access $access, private Compatibility $compatibility, private Catalog $catalog) {}

    public function handle(User $actor, int $tankId, ?LivestockData $candidate = null): CompatibilityReport
    {
        return $this->forTank($this->access->tank($actor, $tankId, Feature::CompatibilityCheck), $candidate);
    }

    /** Dla zbiornika, do którego dostęp już rozstrzygnięto. Ustalenia przyjęte wcześniej dla kandydata są pomijane. */
    public function forTank(Tank $tank, ?LivestockData $candidate = null): CompatibilityReport
    {
        $report = $this->compatibility->check(
            $this->context($tank),
            $this->stock($tank),
            $candidate === null ? null : new StockLine(GuideArea::from($candidate->area->value), $candidate->catalogId, $candidate->quantity),
        );
        if ($candidate === null) {
            return $report;
        }
        $row = Livestock::where(['tank_id' => $tank->id, 'area' => $candidate->area->value, 'catalog_id' => $candidate->catalogId])->first();

        return $report->without($row->acknowledged_findings ?? []);
    }

    /**
     * Liczba uwag dla każdego gatunku fauny z listy, wobec obecnej obsady (plakietka w rankingu).
     *
     * @param  list<string>  $faunaIds
     * @return array<string, int>
     */
    public function notes(User $actor, Tank $tank, array $faunaIds): array
    {
        $this->access->authorize($actor, Feature::CompatibilityCheck);
        $stock = $this->stock($tank);

        return $stock === [] ? [] : $this->compatibility->notesFor($this->context($tank), $stock, $faunaIds);
    }

    private function context(Tank $tank): TankContext
    {
        $co2 = $tank->equipment->contains(fn ($item) => ($this->catalog->find($item->area, $item->catalog_id)?->fields['kategoria_glowna'] ?? null) === 'Dozowanie CO2');

        return new TankContext($tank->size_band, $tank->type->value, $tank->temp_band, $tank->ph_band, $co2);
    }

    /** @return list<StockLine> */
    private function stock(Tank $tank): array
    {
        return $tank->livestock()->orderBy('id')->get()
            ->filter(fn ($row) => in_array($row->area, [Area::Fauna, Area::Flora], true))
            ->map(fn ($row) => new StockLine(GuideArea::from($row->area->value), $row->catalog_id, $row->quantity))->values()->all();
    }
}

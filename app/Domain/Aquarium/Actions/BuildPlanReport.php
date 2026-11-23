<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Advisor\Contracts\Compatibility;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\Compatibility\TankContext;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\PlanReport;
use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\Aquarium\Models\TankPlanItem;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Models\User;

/**
 * Plan z danych katalogu: porównanie obsady przez kontrakt Compatibility (bez własnych reguł) i sprzęt dla pasma i typu planu
 * jako kategorie katalogu do przeglądania. Bez oceny, co jest potrzebne.
 */
class BuildPlanReport
{
    public function __construct(private Access $access, private Compatibility $compatibility, private Catalog $catalog) {}

    public function handle(User $actor, int $planId, ?string $category = null): PlanReport
    {
        $plan = $this->access->plan($actor, $planId);
        $items = $plan->items()->orderBy('id')->get();
        $entries = [];
        foreach (Area::cases() as $area) {
            $ids = $items->where('area', $area)->pluck('catalog_id')->all();
            foreach ($ids === [] ? [] : $this->catalog->snapshot($area, ids: $ids) as $entry) {
                $entries[$area->value.':'.$entry->id] = $entry;
            }
        }

        $stock = $items
            ->filter(fn (TankPlanItem $i) => in_array($i->area, [Area::Fauna, Area::Flora], true))
            ->map(fn (TankPlanItem $i) => new StockLine(GuideArea::from($i->area->value), $i->catalog_id, $i->quantity))
            ->values()
            ->all();
        $co2Dosing = $items
            ->where('area', Area::Equipment)
            ->contains(fn (TankPlanItem $i) => (($entries[Area::Equipment->value.':'.$i->catalog_id] ?? null)?->fields['kategoria_glowna'] ?? null) === 'Dozowanie CO2');
        $report = $this->compatibility->check(new TankContext($plan->size_band, $plan->type->value, $plan->temp_band, $plan->ph_band, $co2Dosing), $stock);

        $byCategory = [];
        foreach ($this->equipmentFor($plan) as $entry) {
            $byCategory[$entry->fields['kategoria_glowna'] ?? ''][] = $entry;
        }
        unset($byCategory['']);
        ksort($byCategory);

        return new PlanReport(
            $items->map(fn ($i) => ['area' => $i->area, 'id' => $i->catalog_id, 'quantity' => $i->quantity, 'entry' => $entries[$i->area->value.':'.$i->catalog_id] ?? null])->all(),
            $report,
            array_map('count', $byCategory),
            $category !== null ? ($byCategory[$category] ?? []) : [],
            $category !== null && isset($byCategory[$category]) ? $category : null,
        );
    }

    /**
     * Rekordy sprzętu, których WielkośćAkwarium zawiera pasmo planu lub „Wszystkie”, a TypAkwarium zawiera typ planu.
     *
     * @return list<CatalogEntry>
     */
    private function equipmentFor(TankPlan $plan): array
    {
        return array_values(array_filter($this->catalog->snapshot(Area::Equipment), function (CatalogEntry $e) use ($plan) {
            $sizes = $e->values['WielkośćAkwarium'] ?? [];

            return (in_array($plan->size_band->label(), $sizes, true) || in_array('Wszystkie', $sizes, true))
                && in_array($plan->type->value, $e->values['TypAkwarium'] ?? [], true);
        }));
    }
}

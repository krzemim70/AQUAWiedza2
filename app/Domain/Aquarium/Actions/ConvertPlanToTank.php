<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\TankData;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\KnowledgeBase\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ConvertPlanToTank
{
    public function __construct(private Access $access, private CreateTank $createTank, private SaveEquipment $equipment) {}

    /**
     * Zamienia plan w zbiornik z obsadą i wyposażeniem jednokrotnie: powtórzone wywołanie zwraca ten sam zbiornik. Limit zbiorników
     * sprawdza CreateTank pod blokadą konta; odmowa cofa całość.
     */
    public function handle(User $actor, int $planId): Tank
    {
        return DB::transaction(function () use ($actor, $planId) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $plan = $this->access->plan($actor, $planId, forUpdate: true);

            if ($plan->state === TankPlan::CONVERTED) {
                return Tank::ownedBy($actor)->find($plan->converted_tank_id)
                    ?? throw ValidationException::withMessages(['plan' => 'Zbiornik utworzony z tego planu został usunięty.']);
            }

            $tank = $this->createTank->handle($actor, new TankData(
                $plan->name,
                $plan->type->value,
                $plan->size_band->value,
                tempBand: $plan->temp_band?->value,
                phBand: $plan->ph_band?->value,
            ));
            if ($plan->liters !== null) {
                $tank->update(['volume_liters' => $plan->liters]);
            }
            $items = $plan->items()->orderBy('id')->get();
            $this->equipment->ensureLimit($items->where('area', Area::Equipment)->count());
            foreach ($items as $item) {
                if ($item->area === Area::Care) {
                    continue;
                }
                $rows = $item->area === Area::Equipment ? $tank->equipment() : $tank->livestock();
                $rows->create(['area' => $item->area, 'catalog_id' => $item->catalog_id, 'quantity' => $item->quantity]);
            }
            $plan->update(['state' => TankPlan::CONVERTED, 'converted_tank_id' => $tank->id]);

            return $tank;
        }, attempts: 3);
    }
}

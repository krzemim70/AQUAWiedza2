<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\Aquarium\Models\TankPlanItem;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class ChangePlanItem
{
    public function __construct(private Access $access, private Catalog $catalog) {}

    /** Ustawia ilość gatunku w planie (0 usuwa). Nowy gatunek musi istnieć w aktywnym katalogu fauny lub flory. Plan zamieniony w zbiornik jest zamknięty. */
    public function handle(User $actor, int $planId, Area $area, string $catalogId, int $quantity): void
    {
        DB::transaction(function () use ($actor, $planId, $area, $catalogId, $quantity) {
            $plan = $this->access->plan($actor, $planId, forUpdate: true);
            $plan->state === TankPlan::DRAFT || throw ValidationException::withMessages(['plan' => 'Ten plan został już zamieniony w zbiornik.']);
            $quantity >= 0 && $quantity <= LivestockData::MAX_QUANTITY
                || throw ValidationException::withMessages(['quantity' => 'Ilość musi mieścić się w przedziale 0–'.LivestockData::MAX_QUANTITY.'.']);
            $key = ['plan_id' => $plan->id, 'area' => $area->value, 'catalog_id' => $catalogId];

            if ($quantity === 0) {
                TankPlanItem::where($key)->delete();

                return;
            }
            in_array($area, [Area::Fauna, Area::Flora], true) && $this->catalog->find($area, $catalogId) !== null
                || throw ValidationException::withMessages(['species' => 'Wybierz gatunek z katalogu.']);

            TankPlanItem::updateOrCreate($key, ['quantity' => $quantity]);
        }, attempts: 3);
    }
}

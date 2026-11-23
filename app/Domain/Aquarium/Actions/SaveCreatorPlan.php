<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\CreatorLine;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\Data\PlanData;
use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zapisuje szkic planu z kreatora (wszystkie cztery obszary katalogu) jako nowy plan
 * albo nadpisuje istniejący własny szkic użytkownika.
 */
class SaveCreatorPlan
{
    public function __construct(
        private Access $access,
        private Catalog $catalog,
        private SaveEquipment $equipment,
        private Settings $settings,
    ) {}

    /**
     * @param  list<CreatorLine>  $lines
     */
    public function handle(User $actor, PlanData $data, array $lines, ?int $replaceDraftPlanId = null): TankPlan
    {
        $this->access->authorize($actor, Feature::TankPlan);
        $attributes = $data->attributes();

        $seen = [];
        /** @var array<string, list<string>> $byArea */
        $byArea = [];
        foreach ($lines as $line) {
            if ($line->quantity < 1 || $line->quantity > LivestockData::MAX_QUANTITY) {
                throw ValidationException::withMessages(['quantity' => 'Ilość musi mieścić się w przedziale 1–'.LivestockData::MAX_QUANTITY.'.']);
            }
            $key = $line->area->value.':'.$line->catalogId;
            if (isset($seen[$key])) {
                throw ValidationException::withMessages(['lines' => 'Pozycje na liście planu nie mogą się powtarzać.']);
            }
            $seen[$key] = true;
            $byArea[$line->area->value][] = $line->catalogId;
        }

        $this->equipment->ensureLimit(count($byArea[Area::Equipment->value] ?? []));

        foreach ($byArea as $areaValue => $ids) {
            $labels = $this->catalog->labels(Area::from($areaValue), $ids);
            foreach ($ids as $id) {
                if (! isset($labels[$id])) {
                    throw ValidationException::withMessages(['lines' => "Pozycja {$id} nie istnieje w aktywnym katalogu."]);
                }
            }
        }

        return DB::transaction(function () use ($actor, $attributes, $lines, $replaceDraftPlanId) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();

            if ($replaceDraftPlanId !== null) {
                $plan = $this->access->plan($actor, $replaceDraftPlanId, forUpdate: true);
                $plan->state === TankPlan::DRAFT || throw ValidationException::withMessages(['plan' => 'Ten plan został już zamieniony w zbiornik.']);
                $plan->update($attributes);
                $plan->items()->delete();
            } else {
                $limit = $this->settings->get(SettingKey::PlanLimit)[$actor->tier()->value] ?? null;
                if ($limit !== null && TankPlan::ownedBy($actor)->where('state', TankPlan::DRAFT)->count() >= $limit) {
                    throw new AuthorizationException("Limit aktywnych planów na koncie {$actor->tier()->label()}: $limit. Usuń plan albo zamień go w zbiornik; Fishroom nie ma limitu.");
                }
                $plan = TankPlan::create($attributes + ['user_id' => $actor->id, 'state' => TankPlan::DRAFT]);
            }

            foreach ($lines as $line) {
                $plan->items()->create([
                    'area' => $line->area->value,
                    'catalog_id' => $line->catalogId,
                    'quantity' => $line->quantity,
                ]);
            }

            return $plan;
        }, attempts: 3);
    }
}

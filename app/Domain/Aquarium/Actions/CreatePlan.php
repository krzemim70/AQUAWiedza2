<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\PlanData;
use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

class CreatePlan
{
    public function __construct(private Access $access, private Settings $settings) {}

    /** Blokada wiersza konta serializuje równoległe tworzenie; limit aktywnych planów (szkiców) z ustawienia, liczony pod blokadą. */
    public function handle(User $actor, PlanData $data): TankPlan
    {
        $this->access->authorize($actor, Feature::TankPlan);
        $attributes = $data->attributes();

        return DB::transaction(function () use ($actor, $attributes) {
            User::whereKey($actor->id)->lockForUpdate()->firstOrFail();
            $limit = $this->settings->get(SettingKey::PlanLimit)[$actor->tier()->value] ?? null;
            if ($limit !== null && TankPlan::ownedBy($actor)->where('state', TankPlan::DRAFT)->count() >= $limit) {
                throw new AuthorizationException("Limit aktywnych planów na koncie {$actor->tier()->label()}: $limit. Usuń plan albo zamień go w zbiornik; Fishroom nie ma limitu.");
            }

            return TankPlan::create($attributes + ['user_id' => $actor->id, 'state' => TankPlan::DRAFT]);
        });
    }
}

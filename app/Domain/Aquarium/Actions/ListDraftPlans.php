<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Models\TankPlan;
use App\Models\User;

/** Zwraca listę własnych szkiców planów użytkownika na potrzeby kreatora. */
class ListDraftPlans
{
    /** @return list<array{id: int, name: string}> */
    public function handle(User $actor): array
    {
        return TankPlan::ownedBy($actor)
            ->where('state', TankPlan::DRAFT)
            ->orderBy('id')
            ->get(['id', 'name'])
            ->map(fn (TankPlan $plan): array => [
                'id' => $plan->id,
                'name' => $plan->name,
            ])
            ->values()
            ->all();
    }
}

<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\TankData;
use App\Domain\Aquarium\Models\Tank;
use App\Models\User;

/** Edycja nie sprawdza limitu: zbiorniki ponad limitem pozostają edytowalne. */
class UpdateTank
{
    public function __construct(private Access $access) {}

    public function handle(User $actor, int $tankId, TankData $data): Tank
    {
        $tank = $this->access->tank($actor, $tankId);
        $tank->update($data->attributes());

        return $tank;
    }
}

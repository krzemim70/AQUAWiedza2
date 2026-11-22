<?php

namespace App\Domain\Advisor\Actions;

use App\Domain\Advisor\Engine\DropEngine;
use App\Domain\Advisor\Engine\Ranking;
use App\Domain\Advisor\Engine\TankProfile;
use App\Domain\Advisor\FaunaPool;

/** Krótki dobór gościa. Typ akwarium jest stały, więc profil go nie podaje. */
class RunDrop
{
    public function __construct(private FaunaPool $pool) {}

    public function handle(TankProfile $profile): Ranking
    {
        $profile = new TankProfile($profile->size, $profile->fish, $profile->temperature, $profile->ph, DropEngine::TANK_TYPE);

        return (new DropEngine)->rank($this->pool->records(), $profile);
    }
}

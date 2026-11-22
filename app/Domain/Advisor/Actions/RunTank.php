<?php

namespace App\Domain\Advisor\Actions;

use App\Domain\Advisor\Engine\Ranking;
use App\Domain\Advisor\Engine\TankEngine;
use App\Domain\Advisor\Engine\TankProfile;
use App\Domain\Advisor\FaunaPool;

/** Ranking dla profilu zbiornika (wejście z formularza lub z zapisanego zbiornika). */
class RunTank
{
    public function __construct(private FaunaPool $pool) {}

    public function handle(TankProfile $profile): Ranking
    {
        return (new TankEngine)->rank($this->pool->records(), $profile);
    }
}

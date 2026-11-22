<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Models\Measurement;
use App\Models\User;

class LatestMeasurement
{
    public function __construct(private Access $access) {}

    public function handle(User $actor, int $tankId): ?Measurement
    {
        return $this->access->tank($actor, $tankId)->latestMeasurement;
    }
}

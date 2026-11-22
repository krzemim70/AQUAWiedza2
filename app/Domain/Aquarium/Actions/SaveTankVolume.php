<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\CalculatorLimits;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\Aquarium\Validation;
use App\Models\User;

/** Zapis litraża z kalkulatora do własnego zbiornika (pasmo wielkości zostaje bez zmian). */
class SaveTankVolume
{
    public function __construct(private Access $access, private CalculatorLimits $limits) {}

    public function handle(User $actor, int $tankId, float $liters): Tank
    {
        $tank = $this->access->tank($actor, $tankId);
        $v = Validation::validate(['liters' => (string) round($liters)], ['liters' => $this->limits->rules('volume_l', integer: true)], ['liters' => 'Litraż']);
        $tank->update(['volume_liters' => (int) $v['liters']]);

        return $tank;
    }
}

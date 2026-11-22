<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\KnowledgeBase\Models\Area;
use App\Models\User;

class ReadTankForCalculators
{
    public function __construct(private Access $access) {}

    /** @return array{volume: int|null, fish: array<string, int>} litraż i ilości fauny (id => ilość) własnego zbiornika */
    public function handle(User $actor, int $tankId): array
    {
        $tank = $this->access->tank($actor, $tankId);

        return [
            'volume' => $tank->volume_liters,
            'fish' => Livestock::where('tank_id', $tank->id)->where('area', Area::Fauna->value)->orderBy('id')->pluck('quantity', 'catalog_id')->all(),
        ];
    }
}

<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class ChangeLivestock
{
    public function __construct(private Access $access, private Catalog $catalog) {}

    /** Ustawia ilość gatunku w obsadzie (0 usuwa). Nowy gatunek musi istnieć w aktywnym katalogu fauny lub flory. */
    public function handle(User $actor, int $tankId, LivestockData $data): void
    {
        $tank = $this->access->tank($actor, $tankId);

        if ($data->quantity < 0 || $data->quantity > 9999) {
            throw ValidationException::withMessages(['quantity' => 'Ilość musi mieścić się w przedziale 0–9999.']);
        }
        $key = ['tank_id' => $tank->id, 'area' => $data->area->value, 'catalog_id' => $data->catalogId];

        if ($data->quantity === 0) {
            Livestock::where($key)->delete();

            return;
        }
        if (! in_array($data->area, [Area::Fauna, Area::Flora], true) || $this->catalog->find($data->area, $data->catalogId) === null) {
            throw ValidationException::withMessages(['species' => 'Wybierz gatunek z katalogu.']);
        }

        Livestock::updateOrCreate($key, ['quantity' => $data->quantity]);
    }
}

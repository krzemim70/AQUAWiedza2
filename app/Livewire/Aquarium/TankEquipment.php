<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TankEquipment extends Component
{
    #[Locked]
    public int $tankId;

    public function mount(int $tankId): void
    {
        $this->tankId = $tankId;
    }

    public function render(Access $access, Catalog $catalog): View
    {
        $tank = $access->tank(auth()->user(), $this->tankId);
        $rows = $tank->equipment()->orderBy('id')->get();
        $names = $catalog->labels(Area::Equipment, $rows->where('area', Area::Equipment)->pluck('catalog_id')->all());
        $query = request()->query('szukaj');
        $phrase = is_string($query) ? mb_substr(trim($query), 0, 100) : '';

        return view('livewire.aquarium.tank-equipment', [
            'rows' => $rows,
            'names' => $names,
            'phrase' => $phrase,
            'results' => $phrase === '' ? [] : $catalog->search($phrase, [Area::Equipment]),
        ]);
    }
}

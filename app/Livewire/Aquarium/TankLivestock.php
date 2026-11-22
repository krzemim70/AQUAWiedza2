<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\ChangeLivestock;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

class TankLivestock extends Component
{
    #[Locked]
    public int $tankId;

    public string $phrase = '';

    public string $quantity = '1';

    /** @var array<int, string> ilość do zmiany, klucz = id wiersza obsady */
    public array $quantities = [];

    public function mount(int $tankId): void
    {
        $this->tankId = $tankId;
        app(Access::class)->tank(auth()->user(), $tankId);
    }

    public function add(ChangeLivestock $change, string $area, string $catalogId): void
    {
        $change->handle(auth()->user(), $this->tankId, new LivestockData(Area::from($area), $catalogId, (int) $this->quantity));
        $this->phrase = '';
    }

    public function changeQuantity(ChangeLivestock $change, int $id): void
    {
        $row = app(Access::class)->tank(auth()->user(), $this->tankId)->livestock()->findOrFail($id);
        $change->handle(auth()->user(), $this->tankId, new LivestockData($row->area, $row->catalog_id, (int) ($this->quantities[$id] ?? $row->quantity)));
        unset($this->quantities[$id]);
    }

    public function remove(ChangeLivestock $change, int $id): void
    {
        $row = app(Access::class)->tank(auth()->user(), $this->tankId)->livestock()->findOrFail($id);
        $change->handle(auth()->user(), $this->tankId, new LivestockData($row->area, $row->catalog_id, 0));
    }

    public function render(Catalog $catalog): View
    {
        $rows = app(Access::class)->tank(auth()->user(), $this->tankId)->livestock()->orderBy('id')->get()
            ->map(fn ($row) => ['row' => $row, 'entry' => $catalog->find($row->area, $row->catalog_id)]);

        return view('livewire.aquarium.tank-livestock', [
            'rows' => $rows,
            'results' => $catalog->search($this->phrase, [Area::Fauna, Area::Flora]),
        ]);
    }
}

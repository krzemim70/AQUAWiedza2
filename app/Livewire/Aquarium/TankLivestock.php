<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\AddToLivestock;
use App\Domain\Aquarium\Actions\ChangeLivestock;
use App\Domain\Aquarium\Actions\CheckLivestockFit;
use App\Domain\Aquarium\Data\LivestockData;
use App\Domain\Aquarium\LivestockFitWarning;
use App\Domain\IdentityAccess\Models\Feature;
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

    /** @var array{area: string, id: string, quantity: int}|null gatunek czekający na potwierdzenie uwag zgodności */
    public ?array $pending = null;

    public function mount(int $tankId): void
    {
        $this->tankId = $tankId;
        app(Access::class)->tank(auth()->user(), $tankId);
    }

    public function add(AddToLivestock $add, string $area, string $catalogId, bool $acknowledge = false): void
    {
        $this->pending = null;
        try {
            $add->handle(auth()->user(), $this->tankId, new LivestockData(Area::from($area), $catalogId, (int) $this->quantity), acknowledge: $acknowledge);
        } catch (LivestockFitWarning) {
            $this->pending = ['area' => $area, 'id' => $catalogId, 'quantity' => (int) $this->quantity];

            return;
        }
        $this->phrase = '';
    }

    public function confirmPending(AddToLivestock $add): void
    {
        if ($this->pending !== null) {
            $this->add($add, $this->pending['area'], $this->pending['id'], acknowledge: true);
        }
    }

    public function cancelPending(): void
    {
        $this->pending = null;
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

    public function render(Catalog $catalog, CheckLivestockFit $fit): View
    {
        $access = app(Access::class);
        $tank = $access->tank(auth()->user(), $this->tankId);
        $report = $access->allows(auth()->user(), Feature::CompatibilityCheck) ? $fit->forTank($tank) : null;
        $confirm = null;
        if ($this->pending !== null) {
            $confirm = ['entry' => $catalog->find(Area::from($this->pending['area']), $this->pending['id']),
                'report' => $fit->forTank($tank, new LivestockData(Area::from($this->pending['area']), $this->pending['id'], $this->pending['quantity']))];
        }
        $rows = $tank->livestock()->orderBy('id')->get()
            ->map(fn ($row) => ['row' => $row, 'entry' => $catalog->find($row->area, $row->catalog_id)]);

        return view('livewire.aquarium.tank-livestock', [
            'rows' => $rows, 'report' => $report, 'confirm' => $confirm,
            'results' => $catalog->search($this->phrase, [Area::Fauna, Area::Flora]),
        ]);
    }
}

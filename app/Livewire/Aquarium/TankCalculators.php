<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Zakładka zbiornika: odnośniki do kalkulatorów z wybranym zbiornikiem. */
class TankCalculators extends Component
{
    #[Locked]
    public int $tankId;

    public function mount(int $tankId): void
    {
        $this->tankId = $tankId;
        app(Access::class)->tank(auth()->user(), $tankId);
    }

    public function render(): View
    {
        return view('livewire.aquarium.tank-calculators');
    }
}

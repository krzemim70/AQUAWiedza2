<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Actions\ListMeasurements;
use App\Domain\Aquarium\MeasurementWarnings;
use App\Domain\Aquarium\Models\Measurement;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Historia parametrów (Fishroom). */
class MeasurementHistory extends Component
{
    #[Locked]
    public int $tankId;

    public function mount(int $tankId, ListMeasurements $list): void
    {
        $this->tankId = $tankId;
        $list->handle(auth()->user(), $tankId);
    }

    public function render(ListMeasurements $list, MeasurementWarnings $warnings): View
    {
        $rows = $list->handle(auth()->user(), $this->tankId)
            ->map(fn (Measurement $m) => ['m' => $m, 'warnings' => $warnings->for($m)]);

        return view('livewire.aquarium.measurement-history', ['rows' => $rows, 'labels' => Measurement::LABELS]);
    }
}

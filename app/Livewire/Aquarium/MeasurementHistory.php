<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\BuildMeasurementTrends;
use App\Domain\Aquarium\Actions\ListMeasurements;
use App\Domain\Aquarium\Calculators\TrendChart;
use App\Domain\Aquarium\MeasurementWarnings;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;

/** Historia parametrów oraz trendy pomiarów (Fishroom). */
class MeasurementHistory extends Component
{
    #[Locked]
    public int $tankId;

    #[Url(as: 'okno')]
    public ?int $windowDays = null;

    public function mount(int $tankId, ListMeasurements $list): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $this->tankId = $tankId;
        $list->handle($actor, $tankId);
    }

    public function render(
        ListMeasurements $list,
        MeasurementWarnings $warnings,
        BuildMeasurementTrends $trends,
        Access $access,
    ): View {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $rows = $list->handle($actor, $this->tankId)
            ->map(fn (Measurement $m): array => ['m' => $m, 'warnings' => $warnings->for($m)]);

        $trendsReport = $access->allows($actor, Feature::MeasurementTrends)
            ? $trends->handle($actor, $this->tankId, $this->windowDays)
            : null;

        $charts = [];
        if ($trendsReport !== null) {
            foreach ($trendsReport->trends as $trend) {
                $charts[$trend->parameter] = $trend->trend->enoughData
                    ? TrendChart::layout($trend->trend, $trend->stockRanges)
                    : null;
            }
        }

        return view('livewire.aquarium.measurement-history', [
            'rows' => $rows,
            'labels' => Measurement::LABELS,
            'trendsReport' => $trendsReport,
            'charts' => $charts,
        ]);
    }
}

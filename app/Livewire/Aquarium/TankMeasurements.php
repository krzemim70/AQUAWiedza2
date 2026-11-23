<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\BuildWaterHints;
use App\Domain\Aquarium\Actions\LatestMeasurement;
use App\Domain\Aquarium\Actions\RecentMeasurements;
use App\Domain\Aquarium\Actions\SaveMeasurement;
use App\Domain\Aquarium\Data\MeasurementData;
use App\Domain\Aquarium\MeasurementWarnings;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\Aquarium\Models\MeasurementMethod;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Ostatni pomiar, skrót ostatnich odczytów i dodawanie pomiaru (Tank i Fishroom). */
class TankMeasurements extends Component
{
    #[Locked]
    public int $tankId;

    public string $measured_at = '';

    public string $method = '';

    public string $ph = '';

    public string $temp_c = '';

    public string $kh = '';

    public string $gh = '';

    public string $no3 = '';

    public string $po4 = '';

    public string $no2 = '';

    public string $nh4 = '';

    public string $ec_us_cm = '';

    /** @var list<string> */
    public array $warnings = [];

    public bool $saved = false;

    public function mount(int $tankId): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        $this->tankId = $tankId;
        app(Access::class)->tank($actor, $tankId);
        $this->measured_at = now()->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i');
    }

    public function save(SaveMeasurement $save, MeasurementWarnings $warnings): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $this->saved = false;
        $this->validate(
            [
                'measured_at' => ['required', 'date_format:Y-m-d\TH:i'],
                'method' => ['nullable', Rule::in(['', ...array_column(MeasurementMethod::cases(), 'value')])],
            ],
            [
                'required' => 'Podaj datę i godzinę pomiaru.',
                'date_format' => 'Podaj poprawną datę i godzinę pomiaru.',
                'in' => 'Wybierz poprawną metodę pomiaru.',
            ],
        );

        $measuredAt = Carbon::createFromFormat('Y-m-d\TH:i', $this->measured_at, config('app.display_timezone')) ?: now();
        $values = [];
        foreach (array_keys(Measurement::LABELS) as $param) {
            $values[$param] = $this->$param;
        }
        $method = $this->method !== '' ? MeasurementMethod::from($this->method) : null;
        $measurement = $save->handle($actor, $this->tankId, new MeasurementData($measuredAt, $values, method: $method));

        $this->warnings = $warnings->for($measurement);
        $this->saved = true;
        $this->reset([...array_keys(Measurement::LABELS), 'method']);
        $this->measured_at = now()->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i');
    }

    public function render(
        LatestMeasurement $latest,
        RecentMeasurements $recent,
        BuildWaterHints $hints,
        MeasurementWarnings $measurementWarnings,
        Access $access,
    ): View {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        $canCompare = $access->allows($actor, Feature::WaterHints);
        $report = $canCompare ? $hints->handle($actor, $this->tankId) : null;
        $recentRows = $recent->handle($actor, $this->tankId, 3)->map(fn (Measurement $m): array => [
            'm' => $m,
            'warnings' => $measurementWarnings->for($m),
        ]);

        return view('livewire.aquarium.tank-measurements', [
            'latest' => $canCompare ? $report?->measurement : $latest->handle($actor, $this->tankId),
            'recent' => $recentRows,
            'waterReport' => $report,
            'canReviewStock' => $access->allows($actor, Feature::CompatibilityCheck),
            'canQuickMeasure' => $access->allows($actor, Feature::QuickMeasurement),
            'canHistory' => $access->allows($actor, Feature::MeasurementHistory),
            'labels' => Measurement::LABELS,
            'methods' => MeasurementMethod::cases(),
        ]);
    }
}

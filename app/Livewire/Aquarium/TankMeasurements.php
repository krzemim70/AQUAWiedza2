<?php

namespace App\Livewire\Aquarium;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Actions\LatestMeasurement;
use App\Domain\Aquarium\Actions\SaveMeasurement;
use App\Domain\Aquarium\Data\MeasurementData;
use App\Domain\Aquarium\MeasurementWarnings;
use App\Domain\Aquarium\Models\Measurement;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;
use Livewire\Attributes\Locked;
use Livewire\Component;

/** Ostatni pomiar i dodawanie pomiaru (Tank i Fishroom). */
class TankMeasurements extends Component
{
    #[Locked]
    public int $tankId;

    public string $measured_at = '';

    public string $ph = '';

    public string $temp_c = '';

    public string $kh = '';

    public string $gh = '';

    public string $no3 = '';

    public string $po4 = '';

    public string $no2 = '';

    public string $nh4 = '';

    /** @var list<string> */
    public array $warnings = [];

    public bool $saved = false;

    public function mount(int $tankId): void
    {
        $this->tankId = $tankId;
        app(Access::class)->tank(auth()->user(), $tankId);
        $this->measured_at = now()->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i');
    }

    public function save(SaveMeasurement $save, MeasurementWarnings $warnings): void
    {
        $this->saved = false;
        $this->validate(
            ['measured_at' => ['required', 'date_format:Y-m-d\TH:i']],
            ['required' => 'Podaj datę i godzinę pomiaru.', 'date_format' => 'Podaj poprawną datę i godzinę pomiaru.'],
        );

        $measuredAt = Carbon::createFromFormat('Y-m-d\TH:i', $this->measured_at, config('app.display_timezone'));
        $values = [];
        foreach (array_keys(Measurement::LABELS) as $param) {
            $values[$param] = $this->$param;
        }
        $measurement = $save->handle(auth()->user(), $this->tankId, new MeasurementData($measuredAt, $values));

        $this->warnings = $warnings->for($measurement);
        $this->saved = true;
        $this->reset(array_keys(Measurement::LABELS));
        $this->measured_at = now()->timezone(config('app.display_timezone'))->format('Y-m-d\TH:i');
    }

    public function render(LatestMeasurement $latest): View
    {
        return view('livewire.aquarium.tank-measurements', [
            'latest' => $latest->handle(auth()->user(), $this->tankId),
            'labels' => Measurement::LABELS,
        ]);
    }
}

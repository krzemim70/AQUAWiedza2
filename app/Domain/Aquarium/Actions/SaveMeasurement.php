<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\MeasurementData;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\Aquarium\Validation;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Validation\ValidationException;

class SaveMeasurement
{
    /** Parametr => [miejsca po przecinku, maks. wartość mieszcząca się w kolumnie]. Zakresy ostrzegawcze (ustawienia) nie odrzucają odczytu. */
    private const COLUMNS = [
        'ph' => [2, 99.99], 'temp_c' => [1, 99.9], 'kh' => [1, 9999.9], 'gh' => [1, 9999.9],
        'no3' => [1, 99999.9], 'po4' => [3, 999.999], 'no2' => [3, 999.999], 'nh4' => [3, 999.999],
    ];

    public function __construct(private Access $access) {}

    public function handle(User $actor, int $tankId, MeasurementData $data): Measurement
    {
        $tank = $this->access->tank($actor, $tankId, Feature::MeasurementWrite);

        $values = [];
        $rules = [];
        foreach (self::COLUMNS as $param => [$decimals, $max]) {
            $raw = trim((string) ($data->values[$param] ?? ''));
            $values[$param] = $raw === '' ? null : str_replace(',', '.', $raw);
            $rules[$param] = ['nullable', 'numeric', "decimal:0,$decimals", 'min:0', "max:$max"];
        }
        $values = Validation::validate($values, $rules, Measurement::LABELS);

        if (array_filter($values, fn ($v) => $v !== null) === []) {
            throw ValidationException::withMessages(['values' => 'Podaj co najmniej jeden parametr.']);
        }

        return Measurement::create(['tank_id' => $tank->id, 'measured_at' => $data->measuredAt->utc()] + $values);
    }
}

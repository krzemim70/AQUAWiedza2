<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\MeasurementData;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\Aquarium\Validation;
use App\Domain\IdentityAccess\Models\Feature;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SaveMeasurement
{
    /** Parametr => [miejsca po przecinku, maks. wartość mieszcząca się w kolumnie]. Zakresy ostrzegawcze (ustawienia) nie odrzucają odczytu. */
    private const COLUMNS = [
        'ph' => [2, 99.99], 'temp_c' => [1, 99.9], 'kh' => [1, 9999.9], 'gh' => [1, 9999.9],
        'no3' => [1, 99999.9], 'po4' => [3, 999.999], 'no2' => [3, 999.999], 'nh4' => [3, 999.999],
        'ec_us_cm' => [1, 999999.9],
    ];

    public function __construct(private Access $access) {}

    public function handle(User $actor, int $tankId, MeasurementData $data): Measurement
    {
        $this->access->tank($actor, $tankId, Feature::MeasurementWrite);

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

        $idempotencyKey = $data->idempotencyKey !== null ? trim($data->idempotencyKey) : null;
        $idempotencyKey = $idempotencyKey === '' ? null : $idempotencyKey;
        $deviceSensorId = $data->deviceSensorId !== null ? trim($data->deviceSensorId) : null;
        $deviceSensorId = $deviceSensorId === '' ? null : $deviceSensorId;

        Validation::validate(
            [
                'idempotency_key' => $idempotencyKey,
                'device_sensor_id' => $deviceSensorId,
            ],
            [
                'idempotency_key' => ['nullable', 'string', 'max:64'],
                'device_sensor_id' => ['nullable', 'string', 'max:64'],
            ],
            [
                'idempotency_key' => 'klucz operacji',
                'device_sensor_id' => 'identyfikator czujnika',
            ],
        );

        return DB::transaction(function () use ($actor, $tankId, $data, $values, $idempotencyKey, $deviceSensorId): Measurement {
            $tank = $this->access->tank($actor, $tankId, Feature::MeasurementWrite, forUpdate: true);

            if ($idempotencyKey !== null) {
                $existing = Measurement::where('tank_id', $tank->id)
                    ->where('idempotency_key', $idempotencyKey)
                    ->first();
                if ($existing !== null) {
                    return $existing;
                }
            }

            return Measurement::create([
                'tank_id' => $tank->id,
                'measured_at' => $data->measuredAt->utc(),
                'source' => $data->source,
                'method' => $data->method,
                'calibration_status' => $data->calibrationStatus,
                'device_sensor_id' => $deviceSensorId,
                'idempotency_key' => $idempotencyKey,
            ] + $values);
        }, attempts: 3);
    }
}

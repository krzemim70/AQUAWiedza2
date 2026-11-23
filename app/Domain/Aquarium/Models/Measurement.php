<?php

namespace App\Domain\Aquarium\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Pomiar to sesja z czasem; NULL w parametrze = brak odczytu. Wartości dziesiętne zwracane jako tekst (bez utraty precyzji).
 *
 * @property int $id
 * @property int $tank_id
 * @property Carbon $measured_at
 * @property MeasurementSource $source
 * @property MeasurementMethod|null $method
 * @property CalibrationStatus|null $calibration_status
 * @property string|null $device_sensor_id
 * @property string|null $idempotency_key
 * @property string|null $ph
 * @property string|null $temp_c
 * @property string|null $kh
 * @property string|null $gh
 * @property string|null $no3
 * @property string|null $po4
 * @property string|null $no2
 * @property string|null $nh4
 * @property string|null $ec_us_cm
 */
#[Table('water_measurements')]
#[Fillable([
    'tank_id', 'measured_at', 'source', 'method', 'calibration_status', 'device_sensor_id', 'idempotency_key',
    'ph', 'temp_c', 'kh', 'gh', 'no3', 'po4', 'no2', 'nh4', 'ec_us_cm',
])]
class Measurement extends Model
{
    public const UPDATED_AT = null;

    /** @var array<string, mixed> */
    protected $attributes = [
        'source' => 'manual',
    ];

    /** Parametr => etykieta. */
    public const LABELS = [
        'ph' => 'pH', 'temp_c' => 'Temperatura (°C)', 'kh' => 'KH', 'gh' => 'GH',
        'no3' => 'NO₃ (mg/l)', 'po4' => 'PO₄ (mg/l)', 'no2' => 'NO₂ (mg/l)', 'nh4' => 'NH₄ (mg/l)',
        'ec_us_cm' => 'Przewodność (µS/cm)',
    ];

    protected function casts(): array
    {
        return [
            'measured_at' => 'datetime',
            'source' => MeasurementSource::class,
            'method' => MeasurementMethod::class,
            'calibration_status' => CalibrationStatus::class,
            'ph' => 'decimal:2', 'temp_c' => 'decimal:1', 'kh' => 'decimal:1', 'gh' => 'decimal:1',
            'no3' => 'decimal:1', 'po4' => 'decimal:3', 'no2' => 'decimal:3', 'nh4' => 'decimal:3',
            'ec_us_cm' => 'decimal:1',
        ];
    }
}

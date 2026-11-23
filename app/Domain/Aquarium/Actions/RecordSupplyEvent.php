<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Calculators\Dosage;
use App\Domain\Aquarium\Calculators\SupplyForecast;
use App\Domain\Aquarium\Data\SupplyEventData;
use App\Domain\Aquarium\Models\SupplyEventKind;
use App\Domain\Aquarium\Models\TankSupplyEvent;
use App\Domain\Aquarium\Validation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Zapisuje zdarzenie zapasu („Zastosowałem” lub „Otwórz nowe opakowanie”) idempotentnie w ramach pozycji.
 */
class RecordSupplyEvent
{
    public function __construct(private Access $access) {}

    public function handle(User $actor, int $supplyId, SupplyEventData $data): TankSupplyEvent
    {
        $idempotencyKey = trim($data->idempotencyKey);
        $deviceSensorId = $data->deviceSensorId !== null ? trim($data->deviceSensorId) : null;

        Validation::validate([
            'idempotency_key' => $idempotencyKey,
            'device_sensor_id' => $deviceSensorId,
        ], [
            'idempotency_key' => ['required', 'string', 'min:1', 'max:64'],
            'device_sensor_id' => ['nullable', 'string', 'max:64'],
        ], [
            'idempotency_key' => 'klucz operacji',
            'device_sensor_id' => 'identyfikator czujnika',
        ]);

        return DB::transaction(function () use ($actor, $supplyId, $data, $idempotencyKey, $deviceSensorId): TankSupplyEvent {
            $supply = $this->access->supply($actor, $supplyId, forUpdate: true);

            if ($supply->archived_at !== null) {
                throw ValidationException::withMessages(['supply' => 'Ta pozycja została zarchiwizowana.']);
            }

            $existing = TankSupplyEvent::where('supply_id', $supply->id)
                ->where('idempotency_key', $idempotencyKey)
                ->first();
            if ($existing !== null) {
                return $existing;
            }

            $at = ($data->occurredAt !== null ? Carbon::instance($data->occurredAt) : now())->utc();
            $tank = $supply->tank()->firstOrFail();

            if ($data->kind === SupplyEventKind::Use) {
                if ($supply->dose_per_liters !== null && ($tank->volume_liters === null || $tank->volume_liters <= 0)) {
                    throw ValidationException::withMessages(['volume_liters' => 'brak danych: uzupełnij litraż zbiornika']);
                }

                $dose = $supply->dose_per_liters !== null
                    ? Dosage::scale((float) $supply->dose_amount, (float) $supply->dose_per_liters, (float) $tank->volume_liters)
                    : (float) $supply->dose_amount;
                $doseRounded = round($dose, 2);
                $delta = -$doseRounded;
                $newRemaining = max(0.0, round((float) $supply->remaining_amount - $doseRounded, 2));
            } else {
                $current = SupplyForecast::calculate(
                    (float) $supply->remaining_amount,
                    $supply->balance_at,
                    (float) $supply->dose_amount,
                    $supply->dose_per_liters !== null ? (float) $supply->dose_per_liters : null,
                    $tank->volume_liters,
                    $supply->interval_days,
                    [],
                    $at,
                )->remainingAmount;
                $delta = round((float) $supply->package_amount, 2);
                $newRemaining = round($current + $delta, 2);
                $supply->low_stock_notified_at = null;
            }

            $supply->remaining_amount = number_format($newRemaining, 2, '.', '');
            $supply->balance_at = $at;
            $supply->save();

            return TankSupplyEvent::create([
                'supply_id' => $supply->id,
                'occurred_at' => $at,
                'delta_amount' => number_format($delta, 2, '.', ''),
                'source' => $data->source,
                'device_sensor_id' => $deviceSensorId !== null && $deviceSensorId !== '' ? $deviceSensorId : null,
                'idempotency_key' => $idempotencyKey,
            ]);
        }, attempts: 3);
    }
}

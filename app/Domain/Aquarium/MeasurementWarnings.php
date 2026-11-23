<?php

namespace App\Domain\Aquarium;

use App\Domain\Aquarium\Models\CalibrationStatus;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;

/** Ostrzeżenia o odczytach spoza zakresów z ustawień oraz nieaktualnej kalibracji (zapis nie jest odrzucany). */
class MeasurementWarnings
{
    public function __construct(private Settings $settings) {}

    /** @return list<string> */
    public function for(Measurement $measurement, ?string $parameter = null): array
    {
        $warnings = [];
        foreach ($this->settings->get(SettingKey::WarningRanges) as $param => $range) {
            if ($parameter !== null && $param !== $parameter) {
                continue;
            }
            $value = $measurement->$param;
            if ($value !== null && ((float) $value < $range['min'] || (float) $value > $range['max'])) {
                $warnings[] = Measurement::LABELS[$param]." = $value jest poza typowym zakresem {$range['min']}–{$range['max']}. Sprawdź odczyt.";
            }
        }

        if ($parameter === null || $parameter === 'ph') {
            $calibrationWarning = match ($measurement->calibration_status) {
                CalibrationStatus::Due => 'Sonda pomiarowa wymaga kalibracji — wynik może być niedokładny.',
                CalibrationStatus::Uncalibrated => 'Sonda pomiarowa jest nieskalibrowana — wynik może być niedokładny.',
                CalibrationStatus::Ok, null => null,
            };
            if ($calibrationWarning !== null) {
                $warnings[] = $calibrationWarning;
            }
        }

        return $warnings;
    }
}

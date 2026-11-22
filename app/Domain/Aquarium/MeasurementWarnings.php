<?php

namespace App\Domain\Aquarium;

use App\Domain\Aquarium\Models\Measurement;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;

/** Ostrzeżenia o odczytach spoza zakresów z ustawień (zapis nie jest odrzucany). */
class MeasurementWarnings
{
    public function __construct(private Settings $settings) {}

    /** @return list<string> */
    public function for(Measurement $measurement): array
    {
        $warnings = [];
        foreach ($this->settings->get(SettingKey::WarningRanges) as $param => $range) {
            $value = $measurement->$param;
            if ($value !== null && ((float) $value < $range['min'] || (float) $value > $range['max'])) {
                $warnings[] = Measurement::LABELS[$param]." = $value jest poza typowym zakresem {$range['min']}–{$range['max']}. Sprawdź odczyt.";
            }
        }

        return $warnings;
    }
}

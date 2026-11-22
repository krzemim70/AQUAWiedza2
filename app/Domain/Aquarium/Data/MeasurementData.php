<?php

namespace App\Domain\Aquarium\Data;

use Carbon\CarbonInterface;

final readonly class MeasurementData
{
    /** @param array<string, string|null> $values parametr (klucz z Measurement::LABELS) => odczyt jako tekst, przecinek dozwolony */
    public function __construct(public CarbonInterface $measuredAt, public array $values) {}
}

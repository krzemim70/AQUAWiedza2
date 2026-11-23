<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Aquarium\Models\CalibrationStatus;
use App\Domain\Aquarium\Models\MeasurementMethod;
use App\Domain\Aquarium\Models\MeasurementSource;
use Carbon\CarbonInterface;

final readonly class MeasurementData
{
    /** @param array<string, string|null> $values parametr (klucz z Measurement::LABELS) => odczyt jako tekst, przecinek dozwolony */
    public function __construct(
        public CarbonInterface $measuredAt,
        public array $values,
        public ?MeasurementMethod $method = null,
        public ?string $idempotencyKey = null,
        public MeasurementSource $source = MeasurementSource::Manual,
        public ?CalibrationStatus $calibrationStatus = null,
        public ?string $deviceSensorId = null,
    ) {}
}

<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Aquarium\Models\SupplyEventKind;
use App\Domain\Aquarium\Models\SupplyEventSource;
use Carbon\CarbonInterface;

final readonly class SupplyEventData
{
    public function __construct(
        public SupplyEventKind $kind,
        public string $idempotencyKey,
        public SupplyEventSource $source = SupplyEventSource::Manual,
        public ?string $deviceSensorId = null,
        public ?CarbonInterface $occurredAt = null,
    ) {}
}

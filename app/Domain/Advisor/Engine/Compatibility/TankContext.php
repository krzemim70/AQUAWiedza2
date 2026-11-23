<?php

namespace App\Domain\Advisor\Engine\Compatibility;

use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TemperatureBand;

/** Zbiornik widziany przez silnik zgodności. Brak pasma wielkości = brak oceny. */
final readonly class TankContext
{
    public function __construct(
        public ?TankBand $size,
        public ?string $type = null,
        public ?TemperatureBand $temperature = null,
        public ?PhBand $ph = null,
        public bool $co2Dosing = false,
    ) {}
}

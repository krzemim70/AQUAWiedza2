<?php

namespace App\Domain\Advisor\Engine;

/** Wejście doboru: wymagane pasmo zbiornika, reszta opcjonalna. Brak pasma ryby oznacza „dopasuj do zbiornika”. */
final readonly class TankProfile
{
    public function __construct(
        public TankBand $size,
        public ?FishBand $fish = null,
        public ?TemperatureBand $temperature = null,
        public ?PhBand $ph = null,
        public ?string $type = null,
    ) {}
}

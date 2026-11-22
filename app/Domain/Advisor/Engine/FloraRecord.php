<?php

namespace App\Domain\Advisor\Engine;

/** Rekord flory w postaci, której potrzebuje silnik. Flora nie ma wymagań wody poza CO2. */
final readonly class FloraRecord
{
    /**
     * @param  list<TankBand>  $tankSizes  rozpoznane pasma akwarium rekordu (bez „Wszystkie”)
     * @param  list<string>  $tankTypes  etykiety typów akwarium rekordu (z „Wszystkie”, jeśli występuje)
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $latinName,
        public ?Co2Need $co2,
        public array $tankSizes,
        public bool $anyTankSize,
        public array $tankTypes,
    ) {}
}

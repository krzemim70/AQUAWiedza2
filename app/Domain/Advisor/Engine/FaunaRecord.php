<?php

namespace App\Domain\Advisor\Engine;

/** Rekord fauny w postaci, której potrzebuje silnik (pasma już rozpoznane, nierozpoznane etykiety pominięte). */
final readonly class FaunaRecord
{
    /**
     * @param  list<PhBand>  $ph
     * @param  list<TankBand>  $tankSizes  rozpoznane pasma akwarium rekordu (bez „Wszystkie”)
     * @param  list<string>  $tankTypes  etykiety typów akwarium rekordu (z „Wszystkie”, jeśli występuje)
     */
    public function __construct(
        public string $id,
        public string $name,
        public string $latinName,
        public string $kind,
        public ?FishBand $size,
        public ?TemperatureBand $temperature,
        public array $ph,
        public array $tankSizes,
        public bool $anyTankSize,
        public array $tankTypes,
        public ?Temperament $temperament = null,
        public ?Zone $zone = null,
    ) {}
}

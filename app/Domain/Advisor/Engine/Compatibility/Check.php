<?php

namespace App\Domain\Advisor\Engine\Compatibility;

/** Kontrole zgodności obsady; wartości to klucze ustawienia `aquarium.compat.severity`. */
enum Check: string
{
    case TankTooSmall = 'tank_too_small';
    case TankType = 'tank_type';
    case TemperaturePair = 'temperature_pair';
    case TemperatureTank = 'temperature_tank';
    case PhPair = 'ph_pair';
    case PhTank = 'ph_tank';
    case Temperament = 'temperament';
    case Co2Missing = 'co2_missing';

    public function label(): string
    {
        return match ($this) {
            self::TankTooSmall => 'Pasmo wielkości zbiornika',
            self::TankType => 'Typ zbiornika',
            self::TemperaturePair => 'Temperatura (pary gatunków)',
            self::TemperatureTank => 'Temperatura wobec ustawień zbiornika',
            self::PhPair => 'pH (pary gatunków)',
            self::PhTank => 'pH wobec ustawień zbiornika',
            self::Temperament => 'Etykiety temperamentu',
            self::Co2Missing => 'CO₂ roślin a sprzęt zbiornika',
        };
    }

    /** Jednostka licznika w tabeli kontroli. */
    public function unit(): string
    {
        return in_array($this, [self::TemperaturePair, self::PhPair], true) ? 'par' : 'gatunków';
    }
}

<?php

namespace App\Domain\KnowledgeBase\Models;

enum Area: string
{
    case Fauna = 'fauna';
    case Flora = 'flora';
    case Equipment = 'equipment';
    case Care = 'care';

    public function label(): string
    {
        return match ($this) {
            self::Fauna => 'Fauna',
            self::Flora => 'Flora',
            self::Equipment => 'Sprzęt',
            self::Care => 'Pielęgnacja',
        };
    }

    public function file(): string
    {
        return match ($this) {
            self::Fauna => 'baza_fauna_end1.txt.php',
            self::Flora => 'baza_flora_end1.txt.php',
            self::Equipment => 'baza_biotop_end2.txt.php',
            self::Care => 'baza_pielegnacja_end1.txt.php',
        };
    }

    public function prefix(): string
    {
        return match ($this) {
            self::Fauna => 'FN',
            self::Flora => 'FL',
            self::Equipment => 'SP',
            self::Care => 'CH',
        };
    }

    /** Plik opisów (tylko fauna i flora). */
    public function descriptionsFile(): ?string
    {
        return match ($this) {
            self::Fauna => 'opisy_fauna.json.php',
            self::Flora => 'opisy_flora.json.php',
            default => null,
        };
    }

    /** @return list<string> dokładna kolejność nagłówków pliku (flora ma inną niż fauna) */
    public function headers(): array
    {
        return match ($this) {
            self::Fauna => ['id', 'Nazwa polska', 'Nazwa łacińska', 'Region', 'Wielkość', 'Temperatura', 'pH', 'Temperament', 'Strefa', 'Typ', 'WielkośćAkwarium', 'TypAkwarium'],
            self::Flora => ['id', 'Nazwa łacińska', 'Nazwa polska', 'Region', 'Wielkość', 'Trudność', 'Strefa', 'CO2', 'Kolor', 'WielkośćAkwarium', 'TypAkwarium'],
            self::Equipment => ['id', 'kategoria_glowna', 'rodzaj', 'producent', 'Marka', 'produkt', 'Poziom_doświadczenia', 'Zdalne_sterowanie', 'WielkośćAkwarium', 'TypAkwarium', 'opis_krotki'],
            self::Care => ['id', 'kategoria_zadania', 'rodzaj_zadania', 'obszar', 'producent', 'Marka', 'produkt', 'opis'],
        };
    }

    /** @return array{name_pl: ?string, name_latin: ?string, product_name: ?string} nagłówki kolumn z nazwami */
    public function nameColumns(): array
    {
        return match ($this) {
            self::Fauna, self::Flora => ['name_pl' => 'Nazwa polska', 'name_latin' => 'Nazwa łacińska', 'product_name' => null],
            default => ['name_pl' => null, 'name_latin' => null, 'product_name' => 'produkt'],
        };
    }

    /** @return list<string> kolumny, które nie są atrybutami filtrowania (opisy tekstowe) */
    public function freeTextColumns(): array
    {
        return match ($this) {
            self::Equipment => ['opis_krotki'],
            self::Care => ['opis'],
            default => [],
        };
    }

    /** @return list<string> kolumny wchodzące do wyszukiwania po nazwie */
    public function searchColumns(): array
    {
        return match ($this) {
            self::Fauna, self::Flora => ['Nazwa polska', 'Nazwa łacińska'],
            self::Equipment => ['rodzaj', 'producent', 'Marka', 'produkt'],
            self::Care => ['rodzaj_zadania', 'producent', 'Marka', 'produkt'],
        };
    }
}

<?php

use App\Domain\IdentityAccess\Models\Feature;

/*
 * Zakładki panelu zbiornika. Każdy plik w tym katalogu zwraca listę zakładek; pliki ładowane są
 * alfabetycznie (prefiks liczbowy = kolejność). Pakiety dodają własny plik, nie edytując cudzych.
 * Zakładka: klucz => [etykieta, funkcja bramkująca (ResolveAccess), nazwa komponentu Livewire z parametrem tank-id].
 */
return [
    'zbiornik' => ['Zbiornik', Feature::TankView, 'aquarium.tank-form'],
    'obsada' => ['Obsada', Feature::TankView, 'aquarium.tank-livestock'],
    'pomiary' => ['Pomiary', Feature::TankView, 'aquarium.tank-measurements'],
    'historia' => ['Historia parametrów', Feature::MeasurementHistory, 'aquarium.measurement-history'],
    'dziennik' => ['Dziennik', Feature::MeasurementHistory, 'aquarium.tank-journal'],
    'przypomnienia' => ['Przypomnienia', Feature::MeasurementHistory, 'aquarium.tank-reminders'],
];

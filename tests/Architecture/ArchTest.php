<?php

arch()->preset()->php();
arch()->preset()->security();

arch('domain stays UI-agnostic')
    ->expect('App\Domain')
    ->not->toUse(['Filament', 'Livewire', 'App\Http']);

arch('advisor engine is pure PHP')
    ->expect('App\Domain\Advisor\Engine')
    ->not->toUse('Illuminate');

arch('aquarium calculators stay pure PHP')
    ->expect('App\Domain\Aquarium\Calculators')
    ->not->toUse(['Illuminate', 'Filament', 'Livewire', 'App\Http']);

arch('no debug calls')->expect(['dd', 'dump', 'ray'])->not->toBeUsed();

arch('env() only in config')->expect('env')->not->toBeUsed()->ignoring('config');

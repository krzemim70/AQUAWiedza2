@extends('layouts.public')

@section('title', 'Doradca akwarystyczny — Aikwarium')
@section('description', 'Doradca akwarystyczny Aikwarium: szybki dobór ryb, kreator zbiornika i ranking obsady dla Twojego akwarium.')
@section('main', 'page-shell advisor-shell')

@section('content')
    <header class="max-w-3xl">
        <h1>Doradca akwarystyczny</h1>
        <p class="lead">Wybierz, jak chcesz dobrać obsadę, rośliny lub sprzęt do akwarium.</p>
    </header>

    <div class="advisor-grid mt-10">
        <article class="card flex flex-col items-start">
            <span class="icon-badge" aria-hidden="true"><x-heroicon-o-squares-plus /></span>
            <h2>Zaplanuj nowe akwarium krok po kroku</h2>
            <p class="text-text-2">Ułóż litraż, obsadę, rośliny, sprzęt i pielęgnację z porównaniem zgodności oraz ofertami sklepów — bez logowania.</p>
            <x-button class="mt-auto" :href="route('creator.show')">Otwórz kreator <x-heroicon-o-arrow-right class="icon" aria-hidden="true" /></x-button>
        </article>

        @foreach ([
            ['Krok po kroku', 'heroicon-o-list-bullet', 'Odpowiedz na kilka pytań. Dobierz ryby, rośliny, sprzęt lub produkty do pielęgnacji albo sprawdź konkretny produkt.', $guidedAccess, 'advisor.guided', 'Uruchom kreator'],
            ['Dobierz do mojego zbiornika', 'heroicon-o-rectangle-stack', 'Użyj parametrów zapisanych w Moim akwarium i zobacz ranking gatunków dla swojego zbiornika.', $tankAccess, 'advisor.tank', 'Wybierz zbiornik'],
        ] as [$title, $icon, $description, $access, $route, $action])
            <article class="card flex flex-col items-start">
                <span class="icon-badge" aria-hidden="true"><x-dynamic-component :component="$icon" /></span>
                <h2>{{ $title }}</h2>
                <p class="text-text-2">{{ $description }}</p>
                @if ($access->allowed)
                    <x-button class="mt-auto" :href="route($route)">{{ $action }} <x-heroicon-o-arrow-right class="icon" aria-hidden="true" /></x-button>
                @else
                    <div class="advisor-access-note mt-auto" data-access="{{ $access->reason }}">
                        <x-heroicon-o-lock-closed class="size-5 shrink-0" aria-hidden="true" />
                        {{ $access->message }}
                    </div>
                @endif
            </article>
        @endforeach

        @if ($quickAccess->allowed && ! $guidedAccess->allowed)
            <article class="card flex flex-col items-start">
                <span class="icon-badge"><x-heroicon-o-bolt aria-hidden="true" /></span>
                <h2>Szybki dobór</h2>
                <p class="text-text-2">Podaj wielkość akwarium, aby zobaczyć propozycję gatunku bez logowania.</p>
                <div class="mt-auto flex flex-wrap gap-3">
                    <x-button :href="route('drop.show')">Dobierz obsadę</x-button>
                    <x-button variant="secondary" :href="route('register')">Załóż konto</x-button>
                </div>
            </article>
        @endif

        @if ($togetherAccess->allowed)
            <article class="card flex flex-col items-start">
                <span class="icon-badge"><x-heroicon-o-scale aria-hidden="true" /></span>
                <h2>Czy te ryby mogą być razem?</h2>
                <p class="text-text-2">Porównaj wymagania temperatury, pH i wielkości zbiornika wybranych gatunków z dowodami z katalogu.</p>
                <x-button class="mt-auto" :href="route('advisor.together')">Porównaj gatunki <x-heroicon-o-arrow-right class="icon" aria-hidden="true" /></x-button>
            </article>
        @endif
    </div>
@endsection

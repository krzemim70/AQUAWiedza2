@extends('layouts.public')

@section('title', 'Aikwarium — dobór ryb i roślin do akwarium')
@section('main', 'home')

@php
    $pozycji = fn (int $n) => $n === 1 ? 'pozycja' : (in_array($n % 10, [2, 3, 4], true) && ! in_array($n % 100, [12, 13, 14], true) ? 'pozycje' : 'pozycji');
    $areas = [
        ['fauna', 'Fauna', 'Ryby i inne zwierzęta dobrane do wielkości zbiornika, temperatury i pH.', 'heroicon-o-heart'],
        ['flora', 'Flora', 'Rośliny z informacją o trudności, świetle i potrzebie CO₂.', 'heroicon-o-sun'],
        ['equipment', 'Sprzęt', 'Filtry, oświetlenie i grzałki pasujące do akwarium.', 'heroicon-o-wrench-screwdriver'],
        ['care', 'Pielęgnacja', 'Środki i zadania do regularnej opieki nad wodą.', 'heroicon-o-beaker'],
    ];
@endphp

@section('content')
    <section class="hero" aria-labelledby="hero-title">
        <div class="hero-glow" aria-hidden="true"></div>
        <div class="container hero-grid">
            <div class="hero-copy">
                <p class="eyebrow">Doradca akwarystyczny</p>
                <h1 id="hero-title">Obsada akwarium dobrana do Twojego zbiornika</h1>
                <p class="lead">Podaj wielkość akwarium. Zobaczysz gatunek, który do niego pasuje.</p>
                <div class="hero-actions">
                    <x-button size="lg" :href="$advisorUrl">Dobierz obsadę <x-heroicon-o-arrow-right aria-hidden="true" class="icon" /></x-button>
                    @guest
                        <x-button size="lg" variant="secondary" :href="route('register')">Załóż konto</x-button>
                    @else
                        <x-button size="lg" variant="secondary" :href="route('aquarium.index')">Moje akwarium</x-button>
                    @endguest
                </div>
            </div>
            <img class="hero-image" src="{{ asset('images/aikwarium-akwarium-1600.jpg') }}"
                srcset="{{ asset('images/aikwarium-akwarium-800.jpg') }} 800w, {{ asset('images/aikwarium-akwarium-1600.jpg') }} 1600w"
                sizes="(min-width: 960px) 540px, 100vw" width="1600" height="1000" fetchpriority="high" decoding="async"
                alt="Akwarium z roślinami, korzeniem i jasnymi promieniami światła w wodzie">
        </div>
    </section>

    <section class="section" aria-labelledby="steps-title">
        <div class="container">
            <h2 id="steps-title">Jak to działa</h2>
            <ol class="grid grid-3 steps">
                <li class="card"><span class="step-no" aria-hidden="true">1</span><h3>Opisz akwarium</h3><p>Wybierz wielkość. Temperaturę, pH i wielkość ryby możesz dodać, jeśli je znasz.</p></li>
                <li class="card"><span class="step-no" aria-hidden="true">2</span><h3>Zobacz dopasowanie</h3><p>Doradca porównuje wymagania gatunków z Twoim zbiornikiem i podaje wynik w procentach.</p></li>
                <li class="card"><span class="step-no" aria-hidden="true">3</span><h3>Kup w lokalnym sklepie</h3><p>Wybierz ofertę sklepu w okolicy i odbierz zakup osobiście.</p></li>
            </ol>
        </div>
    </section>

    <section class="section section-tint" aria-labelledby="areas-title">
        <div class="container">
            <h2 id="areas-title">Cztery obszary doradcy</h2>
            <p class="lead">Jedna baza wiedzy dla całego akwarium.</p>
            <ul class="grid grid-4 areas">
                @foreach ($areas as [$key, $label, $text, $icon])
                    <li class="card">
                        <span class="icon-badge"><x-dynamic-component :component="$icon" aria-hidden="true" /></span>
                        <h3>{{ $label }}</h3>
                        <p>{{ $text }}</p>
                        <p class="count">@if ($counts[$key] > 0){{ number_format($counts[$key], 0, ',', "\u{00a0}") }} {{ $pozycji($counts[$key]) }}@else Katalog w przygotowaniu @endif</p>
                    </li>
                @endforeach
            </ul>
        </div>
    </section>

    <section class="section section-dark" aria-labelledby="tank-title">
        <div class="container split">
            <div>
                <h2 id="tank-title">Moje akwarium</h2>
                <p class="lead">Prywatny panel, w którym trzymasz dane swoich zbiorników.</p>
                @auth
                    <x-button variant="light" size="lg" :href="route('aquarium.index')">Otwórz panel</x-button>
                @else
                    <x-button variant="light" size="lg" :href="route('register')">Załóż konto</x-button>
                @endauth
            </div>
            <ul class="feature-list">
                <li><x-heroicon-o-chart-bar aria-hidden="true" /><div><h3>Pomiary</h3><p>Zapisuj parametry wody i sprawdzaj je z zalecanymi zakresami.</p></div></li>
                <li><x-heroicon-o-book-open aria-hidden="true" /><div><h3>Dziennik</h3><p>Notuj zmiany wody, zakupy i obserwacje.</p></div></li>
                <li><x-heroicon-o-bell aria-hidden="true" /><div><h3>Przypomnienia</h3><p>Ustaw cykliczne zadania, na przykład podmiany wody.</p></div></li>
                <li><x-heroicon-o-calculator aria-hidden="true" /><div><h3>Kalkulator Redfielda</h3><p>Podaj azotany i fosforany, a zobaczysz stosunek N:P.</p></div></li>
            </ul>
        </div>
    </section>

    <section class="section" aria-labelledby="shops-title">
        <div class="container split">
            <div>
                <h2 id="shops-title">Sklepy w okolicy</h2>
                <p class="lead">Oferty lokalnych sklepów akwarystycznych w jednym miejscu.</p>
            </div>
            <ul class="feature-list feature-list-light">
                <li><x-heroicon-o-map-pin aria-hidden="true" /><div><h3>Blisko Ciebie</h3><p>Zobacz, które sklepy mają potrzebny gatunek lub sprzęt.</p></div></li>
                <li><x-heroicon-o-shopping-bag aria-hidden="true" /><div><h3>Odbiór osobisty</h3><p>Wyślij koszyk do sklepu i odbierz zamówienie na miejscu.</p></div></li>
            </ul>
        </div>
    </section>

    <section class="section section-tint" aria-labelledby="plans-title">
        <div class="container">
            <h2 id="plans-title">Plany</h2>
            <ul class="grid grid-3 plans">
                <li class="card">
                    <h3>Drop</h3>
                    <p class="plan-price">Bez konta</p>
                    <p>Jeden szybki dobór dla gościa.</p>
                    <x-button variant="secondary" :href="route('drop.show')">Wypróbuj</x-button>
                </li>
                <li class="card plan-featured">
                    <h3>Tank</h3>
                    <p class="plan-price">Bezpłatnie</p>
                    <p>Konto z jednym zbiornikiem, rankingiem gatunków i kalkulatorem Redfielda.</p>
                    <x-button :href="route('register')">Załóż konto</x-button>
                </li>
                <li class="card">
                    <h3>Fishroom</h3>
                    <p class="plan-price">{{ $price ?? 'Płatny' }}</p>
                    <p>Historia i trendy pomiarów, dziennik, przypomnienia i prowadzony dobór.</p>
                    <x-button variant="secondary" :href="route('register')">Zacznij od konta</x-button>
                </li>
            </ul>
        </div>
    </section>

    <section class="section" aria-labelledby="forshops-title">
        <div class="container">
            <div class="cta-band">
                <x-heroicon-o-building-storefront aria-hidden="true" />
                <div>
                    <h2 id="forshops-title">Masz sklep akwarystyczny?</h2>
                    <p>Dodaj ofertę, pokaż ją klientom z okolicy i przyjmuj zamówienia do odbioru osobistego. Zgłoszenie weryfikujemy przed publikacją.</p>
                </div>
                <x-button size="lg" :href="route('register.create', 'sklep')">Zarejestruj sklep</x-button>
            </div>
        </div>
    </section>

    <section class="section section-tint" aria-labelledby="faq-title">
        <div class="container faq">
            <h2 id="faq-title">Pytania i odpowiedzi</h2>
            <details><summary>Czy muszę mieć konto?</summary><p>Nie. Jeden szybki dobór możesz wykonać bez konta. Konto Tank jest bezpłatne i daje własny zbiornik.</p></details>
            <details><summary>Czy kupuję przez Aikwarium?</summary><p>Nie. Zamówienie trafia do lokalnego sklepu, a płatność i odbiór odbywają się w sklepie.</p></details>
            <details><summary>Skąd są dane o gatunkach?</summary><p>Z katalogu Aikwarium: każdy gatunek ma zakresy temperatury i pH oraz wymagania co do zbiornika.</p></details>
            <details><summary>Co daje plan Fishroom?</summary><p>Historię pomiarów z trendami, dziennik, przypomnienia i prowadzony dobór obsady.</p></details>
        </div>
    </section>
@endsection

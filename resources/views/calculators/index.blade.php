@extends('layouts.public')

@section('title', 'Kalkulatory — Aikwarium')

@section('content')
    <div class="page-hero">
        <h1>Kalkulatory</h1>
        <p class="lead">Proste przeliczenia dla akwarysty. Działają bez konta i nie zapisują wpisanych wartości.</p>
    </div>
    <ul class="grid">
        <li class="card"><h2><a href="{{ route('calculators.volume') }}">Litraż z wymiarów</a></h2><p>Objętość akwarium prostopadłościennego lub walcowatego.</p></li>
        <li class="card"><h2><a href="{{ route('calculators.dosage') }}">Dawkowanie z etykiety</a></h2><p>Przeliczenie dawki producenta na litraż Twojego zbiornika.</p></li>
        <li class="card"><h2><a href="{{ route('calculators.co2') }}">CO₂ z KH i pH</a></h2><p>Przybliżone stężenie CO₂ ze wzoru.</p></li>
        <li class="card"><h2><a href="{{ route('calculators.stocking') }}">Cm ryby na litr</a></h2><p>Orientacyjne obciążenie zbiornika obsadą.</p></li>
        @auth
            <li class="card"><h2><a href="{{ route('aquarium.redfield') }}">Kalkulator Redfielda</a></h2><p>Stosunek azotu do fosforu w wodzie.</p></li>
        @endauth
    </ul>
@endsection

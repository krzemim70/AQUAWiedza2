@extends('layouts.public')

@section('title', 'Cm ryby na litr — Aikwarium')

@section('content')
    <div class="page-hero">
        <p><a href="{{ route('calculators.index') }}">&larr; Kalkulatory</a></p>
        <h1>Cm ryby na litr</h1>
        <p class="lead">Policz orientacyjny wskaźnik z litrażu netto i podanych przez Ciebie długości dorosłych ryb.</p>
    </div>
    @include('calculators._errors')
    @auth
        @if ($tanks !== [])
            <form class="profile-form" method="GET" action="{{ route('calculators.stocking') }}">
                <x-calc-field as="select" name="tank" label="Wczytaj obsadę i litraż zbiornika (opcjonalnie)">
                    <option value="">— wybierz zbiornik —</option>
                    @foreach ($tanks as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($in['tank'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </x-calc-field>
                <button type="submit" class="button-secondary">Wczytaj</button>
            </form>
        @endif
    @endauth
    <form class="profile-form" method="GET" action="{{ route('calculators.stocking') }}#wynik">
        <input type="hidden" name="wiersze" value="{{ $rows }}">
        <x-calc-field name="volume" label="Litraż netto zbiornika (l)" :value="$in['volume'] ?? ''" />
        @for ($i = 0; $i < $rows; $i++)
            @php($n = $i + 1)
            <fieldset class="calc-row">
                <legend>Pozycja {{ $n }}</legend>
                <x-calc-field as="select" name="species[{{ $i }}]" label="Gatunek (opcjonalnie)" sr-suffix="(pozycja {{ $n }})">
                    <option value="">— wybierz gatunek —</option>
                    @foreach ($species as $id => $name)
                        <option value="{{ $id }}" @selected(($in['species'][$i] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </x-calc-field>
                <x-calc-field name="quantity[{{ $i }}]" label="Ilość" sr-suffix="(pozycja {{ $n }})" :value="$in['quantity'][$i] ?? ''" inputmode="numeric" />
                <x-calc-field name="length[{{ $i }}]" label="Długość dorosłej ryby (cm)" sr-suffix="(pozycja {{ $n }})" :value="$in['length'][$i] ?? ''" />
            </fieldset>
        @endfor
        <button type="submit" name="oblicz" value="1">Oblicz</button>
        <button type="submit" name="wiersze" value="{{ min(40, $rows + 4) }}" class="button-secondary">Dodaj 4 pozycje</button>
    </form>

    @include('calculators._result', ['headline' => 'Obciążenie'])
@endsection

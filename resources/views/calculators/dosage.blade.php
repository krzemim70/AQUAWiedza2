@extends('layouts.public')

@section('title', 'Dawkowanie z etykiety — Aikwarium')

@section('content')
    <div class="page-hero">
        <p><a href="{{ route('calculators.index') }}">&larr; Kalkulatory</a></p>
        <h1>Dawkowanie z etykiety</h1>
        <p class="lead">Przeliczamy dawkę z opakowania na Twój litraż. Nie podpowiadamy preparatu, dawki ani częstotliwości.</p>
    </div>
    @include('calculators._errors')
    @auth
        @if ($tanks !== [])
            <form class="profile-form" method="GET" action="{{ route('calculators.dosage') }}">
                <x-calc-field as="select" name="tank" label="Wstaw litraż zapisanego zbiornika (opcjonalnie)">
                    <option value="">— wybierz zbiornik —</option>
                    @foreach ($tanks as $id => $name)
                        <option value="{{ $id }}" @selected((string) ($in['tank'] ?? '') === (string) $id)>{{ $name }}</option>
                    @endforeach
                </x-calc-field>
                <button type="submit" class="button-secondary">Wstaw litraż</button>
                @if (($in['tank'] ?? '') !== '' && $tankVolume === null)
                    <p role="status">Ten zbiornik nie ma zapisanego litrażu. Policz go w <a href="{{ route('calculators.volume') }}">kalkulatorze litrażu</a>.</p>
                @endif
            </form>
        @endif
    @endauth
    <form class="profile-form" method="GET" action="{{ route('calculators.dosage') }}#wynik">
        <fieldset>
            <legend>Dane z opakowania</legend>
            <x-calc-field name="dose" label="Dawka z etykiety" :value="$in['dose'] ?? ''" />
            <x-calc-field as="select" name="unit" label="Jednostka dawki">
                @foreach ($units as $unit)
                    <option value="{{ $unit }}" @selected(($in['unit'] ?? 'ml') === $unit)>{{ $unit }}</option>
                @endforeach
            </x-calc-field>
            <x-calc-field name="label_volume" label="Na ile litrów wody podaje ją etykieta (l)" :value="$in['label_volume'] ?? ''" />
            <x-calc-field name="volume" label="Litraż do przeliczenia (l)" :value="$in['volume'] ?? ''" hint="Cały zbiornik albo objętość podmienianej wody, zależnie od tego, co podaje etykieta." />
        </fieldset>
        <button type="submit" name="oblicz" value="1">Przelicz</button>
    </form>

    @include('calculators._result', ['headline' => 'Przeliczona dawka'])
@endsection

@extends('layouts.public')

@section('title', 'CO₂ z KH i pH — Aikwarium')

@section('content')
    <div class="page-hero">
        <p><a href="{{ route('calculators.index') }}">&larr; Kalkulatory</a></p>
        <h1>CO₂ z KH i pH</h1>
        <p class="lead">Przybliżone stężenie CO₂ ze wzoru. Wynik jest szacunkiem, nie pomiarem.</p>
    </div>
    @include('calculators._errors')
    <form class="profile-form" method="GET" action="{{ route('calculators.co2') }}#wynik">
        <fieldset>
            <legend>Twoje pomiary</legend>
            <x-calc-field name="kh" label="KH (°dKH)" :value="$in['kh'] ?? ''" />
            <x-calc-field name="ph" label="pH" :value="$in['ph'] ?? ''" />
        </fieldset>
        <button type="submit" name="oblicz" value="1">Oszacuj</button>
    </form>

    @include('calculators._result', ['headline' => 'CO₂ ≈', 'decimals' => 1])
@endsection

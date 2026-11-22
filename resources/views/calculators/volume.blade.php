@extends('layouts.public')

@section('title', 'Litraż z wymiarów — Aikwarium')

@section('content')
    @php($shape = $in['shape'] ?? 'cuboid')
    <div class="page-hero">
        <p><a href="{{ route('calculators.index') }}">&larr; Kalkulatory</a></p>
        <h1>Litraż z wymiarów</h1>
        <p class="lead">Wpisz wymiary w centymetrach. Wysokość to poziom lustra wody, nie wysokość szkła.</p>
    </div>
    @include('calculators._errors')
    <form class="profile-form" method="GET" action="{{ route('calculators.volume') }}#wynik">
        <fieldset>
            <legend>Wymiary</legend>
            <x-calc-field as="select" name="shape" label="Kształt akwarium">
                <option value="cuboid" @selected($shape === 'cuboid')>Prostopadłościan</option>
                <option value="cylinder" @selected($shape === 'cylinder')>Walec</option>
            </x-calc-field>
            <x-calc-field name="length" label="Długość (cm), prostopadłościan" :value="$in['length'] ?? ''" />
            <x-calc-field name="width" label="Szerokość (cm), prostopadłościan" :value="$in['width'] ?? ''" />
            <x-calc-field name="diameter" label="Średnica (cm), walec" :value="$in['diameter'] ?? ''" />
            <x-calc-field name="height" label="Wysokość lustra wody (cm)" :value="$in['height'] ?? ''" />
            <x-calc-field name="deduct" label="Litry do odjęcia (opcjonalnie)" :value="$in['deduct'] ?? ''" hint="Np. podłoże i dekoracje. Wpisujesz sam; bez tego pokażemy litraż brutto." />
        </fieldset>
        <button type="submit" name="oblicz" value="1">Oblicz</button>
    </form>

    @include('calculators._result', ['headline' => 'Litraż brutto', 'extra' => view('calculators._volume-extra', compact('result', 'band', 'tanks', 'in', 'saved'))])
@endsection

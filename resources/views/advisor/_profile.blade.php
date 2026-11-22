{{-- Pola profilu zbiornika. $values: stare/bieżące wartości (kody pasm), $tankTypes: opcjonalna lista typów akwarium. --}}
@php
    $fields = [
        'size' => ['Wielkość akwarium', \App\Domain\Advisor\Engine\TankBand::cases(), null, true],
        'fish' => ['Wielkość ryby', \App\Domain\Advisor\Engine\FishBand::cases(), 'Dopasuj do zbiornika', false],
        'temperature' => ['Temperatura', \App\Domain\Advisor\Engine\TemperatureBand::cases(), 'Dowolna', false],
        'ph' => ['pH', \App\Domain\Advisor\Engine\PhBand::cases(), 'Dowolne', false],
    ];
@endphp
@foreach ($fields as $name => [$label, $bands, $empty, $required])
    <p>
        <label for="{{ $name }}">{{ $label }}@if ($required) (wymagane)@endif</label>
        <select id="{{ $name }}" name="{{ $name }}" @required($required) @error($name) aria-invalid="true" aria-describedby="{{ $name }}-error" @enderror>
            @if ($empty !== null || ! isset($values[$name]))
                <option value="">{{ $empty ?? 'Wybierz…' }}</option>
            @endif
            @foreach ($bands as $band)
                <option value="{{ $band->value }}" @selected(($values[$name] ?? null) === $band->value)>{{ $band->label() }}</option>
            @endforeach
        </select>
        @error($name)<span id="{{ $name }}-error" role="alert">{{ $message }}</span>@enderror
    </p>
@endforeach
@isset($tankTypes)
    <p>
        <label for="type">Typ akwarium</label>
        <select id="type" name="type">
            <option value="">Dowolny</option>
            @foreach ($tankTypes as $type)
                <option value="{{ $type }}" @selected(($values['type'] ?? null) === $type)>{{ $type }}</option>
            @endforeach
        </select>
    </p>
@endisset

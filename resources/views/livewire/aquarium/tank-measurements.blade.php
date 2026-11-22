<div>
    <h3>Ostatni pomiar</h3>
    @if ($latest)
        <p>
            Wykonany:
            <time datetime="{{ $latest->measured_at->toIso8601String() }}">{{ $latest->measured_at->timezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</time>
        </p>
        <dl>
            @foreach ($labels as $param => $label)
                @if ($latest->$param !== null)
                    <dt>{{ $label }}</dt>
                    <dd>{{ $latest->$param }}</dd>
                @endif
            @endforeach
        </dl>
    @else
        <p>Brak pomiarów. Dodaj pierwszy poniżej.</p>
    @endif

    <h3>Dodaj pomiar</h3>
    @if ($saved)
        <p role="status">Pomiar zapisany.</p>
    @endif
    @foreach ($warnings as $warning)
        <p role="alert">Uwaga: {{ $warning }}</p>
    @endforeach

    <form wire:submit="save">
        @error('values')
            <p role="alert">{{ $message }}</p>
        @enderror

        <x-field name="measured_at" type="datetime-local" label="Data i godzina pomiaru" required />
        @foreach ($labels as $param => $label)
            <x-field :name="$param" :label="$label" type="text" inputmode="decimal" />
        @endforeach
        <p>Wypełnij co najmniej jeden parametr; puste pola oznaczają brak odczytu.</p>

        <button type="submit" wire:loading.attr="disabled">Zapisz pomiar</button>
    </form>
</div>

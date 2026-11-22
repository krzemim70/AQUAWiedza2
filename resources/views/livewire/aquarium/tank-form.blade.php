<div>
    <form wire:submit="save">
        @error('form')
            <p role="alert">{{ $message }}</p>
        @enderror
        @if ($saved)
            <p role="status">Zapisano zmiany.</p>
        @endif

        <x-field name="name" label="Nazwa zbiornika" maxlength="100" required />

        <x-field name="size_band" label="Wielkość zbiornika" as="select" required>
            <option value="">— wybierz —</option>
            @foreach ($sizes as $size)
                <option value="{{ $size->value }}">{{ $size->label() }}</option>
            @endforeach
        </x-field>

        <x-field name="type" label="Typ akwarium" as="select" required>
            <option value="">— wybierz —</option>
            @foreach ($types as $kind)
                <option value="{{ $kind->value }}">{{ $kind->value }}</option>
            @endforeach
        </x-field>

        <x-field name="fish_size_band" label="Preferowana wielkość ryb (opcjonalnie)" as="select">
            <option value="">— dopasuj do zbiornika —</option>
            @foreach ($fishSizes as $band)
                <option value="{{ $band->value }}">{{ $band->label() }}</option>
            @endforeach
        </x-field>

        <x-field name="temp_band" label="Preferowana temperatura (opcjonalnie)" as="select">
            <option value="">— bez preferencji —</option>
            @foreach ($temps as $band)
                <option value="{{ $band->value }}">{{ $band->label() }}</option>
            @endforeach
        </x-field>

        <x-field name="ph_band" label="Preferowane pH (opcjonalnie)" as="select">
            <option value="">— bez preferencji —</option>
            @foreach ($phs as $band)
                <option value="{{ $band->value }}">{{ $band->label() }}</option>
            @endforeach
        </x-field>

        <x-field name="notes" label="Notatka (opcjonalnie)" as="textarea" maxlength="2000" rows="3" />

        <button type="submit" wire:loading.attr="disabled">{{ $tankId === null ? 'Dodaj zbiornik' : 'Zapisz zbiornik' }}</button>
    </form>

    @if ($tankId !== null)
        <form wire:submit="delete">
            <button type="submit" wire:confirm="Usunąć zbiornik wraz z obsadą, pomiarami, dziennikiem i przypomnieniami? Tej operacji nie można cofnąć.">Usuń zbiornik</button>
        </form>
    @endif
</div>

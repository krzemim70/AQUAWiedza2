<div>
    <h3>Najbliższe przypomnienia</h3>
    @error('form')
        <p role="alert">{{ $message }}</p>
    @enderror
    @forelse ($occurrences as $occurrence)
        <article wire:key="occ-{{ $occurrence->id }}">
            <p>
                <time datetime="{{ $occurrence->due_on->toDateString() }}">{{ $occurrence->due_on->format('d.m.Y') }}</time>
                — {{ $occurrence->reminder->title() }}
                @if ($occurrence->reminder->interval_days)
                    (co {{ $occurrence->reminder->interval_days }} dni)
                @endif
                @if ($occurrence->due_on->toDateString() < $today)
                    <strong>zaległe</strong>
                @endif
            </p>
            <button type="button" wire:click="complete({{ $occurrence->id }})" wire:loading.attr="disabled">Wykonane</button>
            <button type="button" wire:click="edit({{ $occurrence->reminder_id }})">Edytuj</button>
            <button type="button" wire:click="cancel({{ $occurrence->reminder_id }})" wire:confirm="Anulować to przypomnienie?">Anuluj przypomnienie</button>
        </article>
    @empty
        <p>Brak zaległych i nadchodzących przypomnień.</p>
    @endforelse

    <h3>{{ $editingId === null ? 'Nowe przypomnienie' : 'Edycja przypomnienia' }}</h3>
    <form wire:submit="save">
        <x-field name="type" label="Rodzaj" as="select" required>
            @foreach ($types as $kind)
                <option value="{{ $kind->value }}">{{ $kind->label() }}</option>
            @endforeach
        </x-field>
        <x-field name="label" label="Nazwa (opcjonalnie)" maxlength="100" />
        <x-field name="due_on" type="date" label="Najbliższy termin" required />
        <x-field name="interval_days" type="number" label="Powtarzaj co (dni, puste = jednorazowe)" min="1" />
        <button type="submit" wire:loading.attr="disabled">{{ $editingId === null ? 'Dodaj przypomnienie' : 'Zapisz zmiany' }}</button>
        @if ($editingId !== null)
            <button type="button" wire:click="cancelEdit">Anuluj edycję</button>
        @endif
    </form>
</div>

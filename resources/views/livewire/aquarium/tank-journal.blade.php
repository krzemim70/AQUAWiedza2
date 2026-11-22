<div>
    <form wire:submit="save">
        <x-field name="entry_date" type="date" label="Data" required />
        <x-field name="content" as="textarea" label="Treść wpisu" rows="4" maxlength="5000" required />
        <button type="submit" wire:loading.attr="disabled">Dodaj wpis</button>
    </form>

    @forelse ($entries as $entry)
        <article wire:key="journal-{{ $entry->id }}">
            <h3><time datetime="{{ $entry->entry_date->toDateString() }}">{{ $entry->entry_date->format('d.m.Y') }}</time></h3>
            <p>{!! nl2br(e($entry->content)) !!}</p>
        </article>
    @empty
        <p>Dziennik jest pusty.</p>
    @endforelse
</div>

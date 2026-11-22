<div>
    @error('species')
        <p role="alert">{{ $message }}</p>
    @enderror

    <h3>Obsada</h3>
    @forelse ($rows as $item)
        <form wire:submit="changeQuantity({{ $item['row']->id }})" wire:key="livestock-{{ $item['row']->id }}">
            <p>
                {{ $item['entry']?->name ?? $item['row']->catalog_id }}
                @if ($item['entry']?->nameLatin)
                    <i>({{ $item['entry']->nameLatin }})</i>
                @elseif ($item['entry'] === null)
                    (pozycja wycofana z katalogu)
                @endif
            </p>
            <label for="qty-{{ $item['row']->id }}">Ilość</label>
            <input id="qty-{{ $item['row']->id }}" type="number" min="0" max="9999" wire:model="quantities.{{ $item['row']->id }}" placeholder="{{ $item['row']->quantity }}">
            <button type="submit">Zmień ilość</button>
            <button type="button" wire:click="remove({{ $item['row']->id }})">Usuń z obsady</button>
        </form>
    @empty
        <p>Obsada jest pusta. Wyszukaj gatunek poniżej.</p>
    @endforelse

    <h3>Dodaj gatunek</h3>
    <div>
        <label for="livestock-phrase">Szukaj ryby, bezkręgowca lub rośliny</label>
        <input id="livestock-phrase" type="search" wire:model.live.debounce.300ms="phrase" autocomplete="off">
    </div>
    <div>
        <label for="livestock-quantity">Ilość</label>
        <input id="livestock-quantity" type="number" min="1" max="9999" wire:model="quantity">
        @error('quantity')
            <p role="alert">{{ $message }}</p>
        @enderror
    </div>
    @if ($phrase !== '')
        <ul aria-label="Wyniki wyszukiwania">
            @forelse ($results as $entry)
                <li wire:key="result-{{ $entry->area->value }}-{{ $entry->id }}">
                    {{ $entry->name }} @if ($entry->nameLatin)<i>({{ $entry->nameLatin }})</i>@endif
                    <button type="button" wire:click="add('{{ $entry->area->value }}', '{{ $entry->id }}')">Dodaj</button>
                </li>
            @empty
                <li>Brak wyników (wpisz co najmniej kilka liter nazwy).</li>
            @endforelse
        </ul>
    @endif
</div>

<div>
    <p>Podpowiadamy rodzaj przypomnienia tylko wtedy, gdy opis rekordu z Twojej obsady lub sprzętu mówi o tej czynności. Katalog nie podaje terminów ani interwałów, więc ustalasz je sam w formularzu.</p>
    @forelse ($hints as $hint)
        <x-card class="mb-4" wire:key="hint-{{ $hint->type->value }}">
            <h3>{{ $hint->type->label() }}</h3>
            <ul>
                @foreach ($hint->sources as $source)
                    <li>
                        {{ $source['name'] }}
                        <x-why :evidence="[$source['evidence']]" />
                    </li>
                @endforeach
            </ul>
            <x-button :href="route('aquarium.tank', ['tank' => $tankId, 'karta' => 'przypomnienia', 'typ' => $hint->type->value])" variant="secondary" :aria-label="'Dodaj przypomnienie: '.$hint->type->label()">Dodaj przypomnienie</x-button>
        </x-card>
    @empty
        <p>Brak podpowiedzi: żaden rekord obsady ani sprzętu tego zbiornika nie podaje czynności do przypomnienia. Własne przypomnienie dodasz w zakładce „Przypomnienia”.</p>
    @endforelse
</div>

<x-filament-widgets::widget>
    @if ($names->isNotEmpty())
        <x-filament::section icon="heroicon-o-exclamation-triangle" icon-color="warning">
            <x-slot name="heading">Uzupełnij miasto placówki</x-slot>
            <p>Placówki bez miasta: {{ $names->implode(', ') }}. Klienci nie zobaczą, gdzie odebrać zamówienie.</p>
            @if ($url)
                <p><a href="{{ $url }}" class="fi-link">Przejdź do edycji placówek</a></p>
            @else
                <p>Poproś właściciela sklepu o uzupełnienie miasta w placówkach.</p>
            @endif
        </x-filament::section>
    @endif
</x-filament-widgets::widget>

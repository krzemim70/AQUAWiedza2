<div>
    <h1>Kalkulator Redfielda</h1>
    <p>Stosunek azotu do fosforu (N:P) w wodzie na podstawie stężeń NO₃ i PO₄. Wynik nie zapisuje pomiaru.</p>

    @if ($tanks->isNotEmpty())
        <form wire:submit="prefill">
            <x-field name="tank" label="Wczytaj ostatni pomiar ze zbiornika (opcjonalnie)" as="select">
                <option value="">— wybierz zbiornik —</option>
                @foreach ($tanks as $item)
                    <option value="{{ $item->id }}">{{ $item->name }}</option>
                @endforeach
            </x-field>
            <button type="submit">Wczytaj wartości</button>
            @if ($notice !== '')
                <p role="status">{{ $notice }}</p>
            @endif
        </form>
    @endif

    <form wire:submit="calculate">
        <x-field name="no3" label="NO₃ (mg/l)" type="text" inputmode="decimal" required />
        <x-field name="po4" label="PO₄ (mg/l)" type="text" inputmode="decimal" required />
        <button type="submit">Oblicz</button>
    </form>

    @if ($result)
        <section aria-labelledby="redfield-result" role="status">
            <h2 id="redfield-result">Wynik: {{ $result['zone'] }}</h2>
            <p data-signal="{{ $result['signal'] }}">{{ $result['message'] }}</p>
            <dl>
                <dt>Stosunek molowy N:P</dt>
                <dd>{{ number_format($result['molar'], 2, ',', '') }}</dd>
                <dt>Stosunek masowy NO₃:PO₄</dt>
                <dd>{{ number_format($result['mass'], 2, ',', '') }}</dd>
            </dl>
        </section>
    @endif
</div>

{{-- Obszar wyniku zawsze w DOM-ie (aria-live), żeby czytnik ogłosił zmianę. --}}
<div id="wynik" aria-live="polite" aria-atomic="true">
    @if ($result)
        <section class="result-card" aria-labelledby="wynik-naglowek">
            <h2 id="wynik-naglowek">
                @if ($result->value === null)
                    Brak wyniku
                @else
                    {{ $headline ?? 'Wynik' }}: {{ \App\Domain\Aquarium\Data\CalculatorResult::fmt($result->value, $decimals ?? 2) }} {{ $result->unit }}
                @endif
            </h2>
            {{ $extra ?? '' }}
            <details open>
                <summary>Jak policzono</summary>
                <ol>
                    @foreach ($result->steps as $step)
                        <li>{{ $step }}</li>
                    @endforeach
                </ol>
            </details>
            @foreach ($result->notes as $note)
                <p><small>{{ $note }}</small></p>
            @endforeach
        </section>
    @endif
</div>

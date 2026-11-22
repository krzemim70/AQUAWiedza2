@if (count($offers) > 0)
    <section aria-labelledby="offers-heading">
        <h3 id="offers-heading">Gdzie kupić</h3>
        <ul>
            @foreach ($offers as $offer)
                <li>
                    {{ $offer->shopName }}, {{ $offer->locationName }} ({{ $offer->locationCity }})
                    @if ($offer->variant !== '') — {{ $offer->variant }} @endif
                    — {{ number_format($offer->priceGrosze / 100, 2, ',', ' ') }} zł,
                    @if ($offer->freeQty > 0)
                        dostępna ilość: {{ $offer->freeQty }}
                    @else
                        chwilowo niedostępna
                    @endif
                    (oferta ważna do {{ $offer->validTo->timezone(config('app.display_timezone'))->format('d.m.Y') }})
                </li>
            @endforeach
        </ul>
    </section>
@endif

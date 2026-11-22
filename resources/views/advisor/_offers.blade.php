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
                    @if ($offer->freeQty > 0)
                        <form method="POST" action="{{ route('cart.add') }}">
                            @csrf
                            <input type="hidden" name="offer_id" value="{{ $offer->id }}">
                            <input type="number" name="qty" value="1" min="1" max="{{ $offer->freeQty }}" aria-label="Ilość">
                            <x-button>Dodaj do koszyka</x-button>
                        </form>
                    @endif
                </li>
            @endforeach
        </ul>
    </section>
@endif

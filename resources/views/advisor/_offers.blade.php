<x-card>
    @if ($productPage)<h2>Gdzie kupić</h2>@else<h3>Gdzie kupić</h3>@endif

    @forelse ($offers as $offer)
        @if ($loop->first)<ul class="offer-list m-0 list-none divide-y divide-line p-0">@endif
        <li class="flex flex-wrap items-center justify-between gap-3 py-4">
            <div>
                <strong>{{ $offer->shopName }}, {{ $offer->locationName }} ({{ $offer->locationCity }})</strong>
                @if ($offer->variant !== '')<div>Wariant: {{ $offer->variant }}</div>@endif
                <div>
                    <strong>{{ number_format($offer->priceGrosze / 100, 2, ',', ' ') }} zł</strong>
                    <span class="text-muted">
                        @if ($offer->freeQty > 0)
                            · {{ $productPage ? 'Dostępna' : 'dostępna' }} ilość: {{ $offer->freeQty }}
                        @else
                            · Chwilowo niedostępna
                        @endif
                    </span>
                </div>
                <small>Oferta ważna do {{ $offer->validTo->timezone(config('app.display_timezone'))->format('d.m.Y') }}</small>
            </div>
            @if ($canCart && $offer->freeQty > 0)
                <form method="POST" action="{{ route('cart.add') }}" class="flex flex-wrap items-end gap-2">
                    @csrf
                    <input type="hidden" name="offer_id" value="{{ $offer->id }}">
                    <label class="!m-0 w-20">
                        Ilość
                        <input type="number" name="qty" value="1" min="1" max="{{ $offer->freeQty }}" required>
                    </label>
                    <x-button type="submit" :aria-label="'Dodaj do koszyka: '.$name.' — '.$offer->shopName">Dodaj do koszyka</x-button>
                </form>
            @endif
        </li>
        @if ($loop->last)</ul>@endif
    @empty
        <p class="!mb-0 text-muted">{{ $productPage ? 'Brak ofert w sklepach.' : 'Brak ofert w sklepach w okolicy' }}</p>
    @endforelse

    @if ($guest && $availableOffer)
        <x-button :href="$loginUrl" variant="secondary" :aria-label="'Zaloguj się, aby kupić: '.$name">Zaloguj się, aby kupić</x-button>
    @endif
    @include('watches._card-action')
</x-card>

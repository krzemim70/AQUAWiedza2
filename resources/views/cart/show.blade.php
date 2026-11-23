@extends('layouts.public')

@section('title', 'Koszyk — Aikwarium')

@section('content')
    <h1>Koszyk</h1>

    @if (session('status'))
        <x-alert>{{ session('status') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert type="error" role="alert">
            @foreach ($errors->all() as $message)
                <p>{{ $message }}</p>
            @endforeach
        </x-alert>
    @endif

    @if ($groups->isEmpty())
        <x-empty-state>Twój koszyk jest pusty. Ofert szukaj w <a href="{{ route('advisor.tank') }}">Doradcy</a>, w sekcji „Gdzie kupić”.</x-empty-state>
    @else
        @foreach ($groups as $lines)
            @php($first = $lines->first())
            <x-card class="cart-shop">
                <h2>{{ $first->shopName }}</h2>
                @php($rating = $lines->first(fn ($line) => $line->shopRating !== null)?->shopRating)
                @if ($rating !== null)
                    <x-shop-rating :average="$rating->average" :count="$rating->count"
                        :shop-id="$first->shopId" :shop-name="$first->shopName" />
                @endif
                <p>Odbiór osobisty: {{ $first->locationName }}, {{ $first->locationAddress }}</p>
                <div class="table-scroll">
                    <table>
                        <thead>
                            <tr><th>Pozycja</th><th>Cena</th><th>Ilość</th><th>Razem</th><th></th></tr>
                        </thead>
                        <tbody>
                            @foreach ($lines as $line)
                                <tr>
                                    <td>
                                        {{ $line->name }}@if ($line->variant !== '') ({{ $line->variant }})@endif
                                        @if ($line->issue)
                                            <br><small role="alert">{{ $line->issue }}</small>
                                        @endif
                                    </td>
                                    <td>
                                        @if ($line->offer)<x-offer-price :offer="$line->offer" :strong="false" />@else{{ number_format($line->priceGrosze / 100, 2, ',', ' ') }} zł @endif
                                    </td>
                                    <td>
                                        <form method="POST" action="{{ route('cart.update', $line->offerId) }}">
                                            @csrf @method('PATCH')
                                            <input type="number" name="qty" value="{{ $line->qty }}" min="0" aria-label="Ilość: {{ $line->name }}">
                                            <small>dostępna ilość: {{ $line->freeQty }}</small>
                                            <x-button variant="secondary">Zmień</x-button>
                                        </form>
                                    </td>
                                    <td>{{ number_format($line->total() / 100, 2, ',', ' ') }} zł</td>
                                    <td>
                                        <form method="POST" action="{{ route('cart.remove', $line->offerId) }}">
                                            @csrf @method('DELETE')
                                            <x-button variant="secondary">Usuń</x-button>
                                        </form>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
                <p><strong>Suma w sklepie: {{ number_format($lines->sum(fn ($l) => $l->total()) / 100, 2, ',', ' ') }} zł</strong></p>
            </x-card>
        @endforeach

        <p><strong>Razem: {{ number_format($total / 100, 2, ',', ' ') }} zł</strong></p>

        @if ($priceChanged)
            <form method="POST" action="{{ route('cart.prices') }}">
                @csrf
                <x-button variant="secondary">Zaakceptuj aktualne ceny</x-button>
            </form>
        @endif

        <x-card>
            <h2>Wyślij do sklepów</h2>
            <p>Powstanie {{ $groups->count() }} {{ $groups->count() === 1 ? 'zamówienie' : 'zamówień' }} (po jednym w każdym sklepie). Sklep zdecyduje o całym zamówieniu. Dopiero po decyzji dostaniesz numer zamówienia i dane do przelewu; płacisz poza platformą, towar odbierasz osobiście.</p>
            <p>Każdy sklep zobaczy Twój nick ({{ auth()->user()->name }}), adres e-mail ({{ auth()->user()->email }}) i telefon podany poniżej. Nie przekazujemy imienia, nazwiska ani adresu.</p>
            <form method="POST" action="{{ route('cart.submit') }}">
                @csrf
                <input type="hidden" name="key" value="{{ $key }}">
                <div class="field">
                    <label for="phone">Telefon kontaktowy</label>
                    <input id="phone" type="tel" name="phone" value="{{ old('phone') }}" required maxlength="32">
                    @error('phone')<p role="alert">{{ $message }}</p>@enderror
                </div>
                <x-button :disabled="$blocked">Wyślij do sklepów</x-button>
            </form>
        </x-card>
    @endif
@endsection

@extends('layouts.public')

@section('title', 'Zamówienie — Aikwarium')

@section('content')
    <p><a href="{{ route('orders.index') }}">Wszystkie zamówienia</a></p>
    <h1>Zamówienie w sklepie {{ $order->shop->name }}</h1>

    @if (session('status'))
        <x-alert>{{ session('status') }}</x-alert>
    @endif
    @if ($errors->any())
        <x-alert type="error" role="alert">{{ $errors->first() }}</x-alert>
    @endif

    <x-card>
        <h2>{{ $order->state->label() }}</h2>
        @if ($order->state->value === 'new')
            <p>Sklep jeszcze nie zdecydował. Dostępna ilość może się zmienić do czasu decyzji sklepu.</p>
        @elseif ($order->state->value === 'rejected')
            <p>Powód odrzucenia: {{ $order->rejection_code === 'inne' ? $order->note : (\App\Domain\Shops\Models\Order::REJECTION_CODES[$order->rejection_code] ?? '—') }}</p>
            <p>Numer sprawy: <strong>{{ $order->ean13 }}</strong></p>
        @elseif ($order->state->value === 'confirmed')
            <p>Zamówienie potwierdzone. Zapłać przelewem poza platformą i odbierz towar osobiście.</p>
        @elseif ($order->state->value === 'ready')
            <p>Odbiór: {{ $order->location->name }}, {{ $order->location->address }}, {{ $order->location->city }}@if ($order->location->opening_hours) ({{ $order->location->opening_hours }})@endif.</p>
        @endif

        @if ($order->ean13 && $order->state->value !== 'rejected')
            <p>Numer zamówienia i tytuł przelewu: <strong id="order-number">{{ $order->ean13 }}</strong>
                <x-button type="button" variant="secondary" onclick="navigator.clipboard.writeText('{{ $order->ean13 }}')">Kopiuj tytuł przelewu</x-button></p>
            <p>Kwota: <strong>{{ number_format($order->total_grosze / 100, 2, ',', ' ') }} zł</strong>. Odbiorca: {{ $order->shop->name }}.</p>
        @endif
    </x-card>

    <x-card>
        <h2>Pozycje</h2>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Pozycja</th><th>Cena</th><th>Ilość</th><th>Razem</th></tr></thead>
                <tbody>
                    @foreach ($order->items as $item)
                        <tr>
                            <td>{{ $item->name }}@if ($item->variant !== '') ({{ $item->variant }})@endif</td>
                            <td>{{ number_format($item->price_grosze / 100, 2, ',', ' ') }} zł</td>
                            <td>{{ $item->qty }}</td>
                            <td>{{ number_format($item->price_grosze * $item->qty / 100, 2, ',', ' ') }} zł</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        <p><strong>Suma: {{ number_format($order->total_grosze / 100, 2, ',', ' ') }} zł</strong></p>
    </x-card>

    <x-card>
        <h2>Przebieg</h2>
        <ol>
            @foreach ($order->events as $event)
                <li>
                    {{ $event->occurred_at->timezone(config('app.display_timezone'))->format('d.m.Y H:i') }} — {{ $event->to_state->label() }}
                </li>
            @endforeach
        </ol>
    </x-card>

    @if ($order->state->value === 'new')
        <form method="POST" action="{{ route('orders.close', $order->id) }}">
            @csrf
            <x-button variant="secondary" onclick="return confirm('Wycofać to zamówienie?')">Wycofaj zamówienie</x-button>
        </form>
    @endif
@endsection

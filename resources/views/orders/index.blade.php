@extends('layouts.public')

@section('title', 'Moje zamówienia — Aikwarium')

@section('content')
    <h1>Moje zamówienia</h1>

    @if (session('status'))
        <x-alert>{{ session('status') }}</x-alert>
    @endif

    @if ($orders->isEmpty())
        <x-empty-state>Nie masz jeszcze zamówień. Dodaj oferty do <a href="{{ route('cart.show') }}">koszyka</a>.</x-empty-state>
    @else
        <div class="table-scroll">
            <table>
                <thead>
                    <tr><th>Data</th><th>Sklep</th><th>Status</th><th>Numer</th><th>Suma</th><th></th></tr>
                </thead>
                <tbody>
                    @foreach ($orders as $order)
                        <tr>
                            <td>{{ $order->created_at->timezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</td>
                            <td>{{ $order->shop->name }}</td>
                            <td>{{ $order->state->label() }}</td>
                            <td>{{ $order->ean13 ?? '—' }}</td>
                            <td>{{ number_format($order->total_grosze / 100, 2, ',', ' ') }} zł</td>
                            <td><a href="{{ route('orders.show', $order->id) }}">Szczegóły</a></td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif
@endsection

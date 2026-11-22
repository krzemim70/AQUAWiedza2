@extends('layouts.public')

@section('title', 'Potwierdzenie adresu — Aikwarium')

@section('content')
    <h1>Potwierdź adres e-mail</h1>
    @include('auth._messages')
    <p>Kliknij link z wiadomości, którą wysłaliśmy po rejestracji. Możesz zamówić nowy link.</p>
    <form method="POST" action="{{ route('verification.send') }}">
        @csrf
        <p><label>E-mail <input type="email" name="email" value="{{ old('email') }}" required></label></p>
        <p><button type="submit">Wyślij ponownie</button></p>
    </form>
@endsection

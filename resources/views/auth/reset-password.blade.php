@extends('layouts.public')

@section('title', 'Nowe hasło — Aikwarium')

@section('content')
    <h1>Nowe hasło</h1>
    @include('auth._messages')
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <p><label>E-mail <input type="email" name="email" value="{{ old('email', $email) }}" required></label></p>
        <p><label>Nowe hasło <input type="password" name="password" required autocomplete="new-password"></label></p>
        <p><label>Powtórz hasło <input type="password" name="password_confirmation" required autocomplete="new-password"></label></p>
        <p><button type="submit">Zmień hasło</button></p>
    </form>
@endsection

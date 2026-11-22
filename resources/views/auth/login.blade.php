@extends('layouts.public')

@section('title', 'Logowanie — Aikwarium')

@section('content')
    <h1>Logowanie</h1>
    @include('auth._messages')
    <form method="POST" action="/logowanie">
        @csrf
        <p><label>E-mail <input type="email" name="email" value="{{ old('email') }}" required autofocus></label></p>
        <p><label>Hasło <input type="password" name="password" required></label></p>
        <p><label><input type="checkbox" name="remember" value="1"> Zapamiętaj mnie</label></p>
        <p><button type="submit">Zaloguj</button></p>
    </form>
    <p><a href="{{ route('password.request') }}">Nie pamiętam hasła</a> · <a href="{{ route('register') }}">Załóż konto</a> · <a href="{{ route('verification.notice') }}">Nie dotarł link potwierdzający?</a></p>
@endsection

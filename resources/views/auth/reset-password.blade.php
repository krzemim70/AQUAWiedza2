@extends('layouts.public')

@section('title', 'Nowe hasło — Aikwarium')

@section('content')
<div class="auth-card">
    <h1>Nowe hasło</h1>
    @include('auth._messages')
    <form method="POST" action="{{ route('password.update') }}">
        @csrf
        <input type="hidden" name="token" value="{{ $token }}">
        <p><label>E-mail <input type="email" name="email" value="{{ old('email', $email) }}" required></label> @error("email")<span class="field-error" role="alert">{{ $message }}</span>@enderror</p>
        <p><label>Nowe hasło <input type="password" name="password" required autocomplete="new-password"></label> @error("password")<span class="field-error" role="alert">{{ $message }}</span>@enderror</p>
        <p><label>Powtórz hasło <input type="password" name="password_confirmation" required autocomplete="new-password"></label> @error("password_confirmation")<span class="field-error" role="alert">{{ $message }}</span>@enderror</p>
        <p><button type="submit">Zmień hasło</button></p>
    </form>
</div>
@endsection

@extends('layouts.public')

@section('title', 'Logowanie — Aikwarium')

@section('content')
<div class="auth-card">
    <h1>Logowanie</h1>
    @include('auth._messages')
    <form method="POST" action="/logowanie">
        @csrf
        <p><label>E-mail <input type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username" @error('email') aria-invalid="true" aria-describedby="login-email-error" @enderror></label> @error('email')<span id="login-email-error" class="field-error">{{ $message }}</span>@enderror</p>
        <p><label>Hasło <input type="password" name="password" required autocomplete="current-password" @error('password') aria-invalid="true" aria-describedby="login-password-error" @enderror></label> @error('password')<span id="login-password-error" class="field-error">{{ $message }}</span>@enderror</p>
        <p><label><input type="checkbox" name="remember" value="1"> Zapamiętaj mnie</label></p>
        <p><x-button type="submit">Zaloguj</x-button></p>
    </form>
    <p><a href="{{ route('password.request') }}">Nie pamiętam hasła</a> · <a href="{{ route('register') }}">Załóż konto</a> · <a href="{{ route('verification.notice') }}">Nie dotarł link potwierdzający?</a></p>
</div>
@endsection

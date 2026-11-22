@extends('layouts.public')

@section('title', 'Rejestracja — Aikwarium')

@section('content')
    <h1>{{ $shop ? 'Rejestracja sklepu' : 'Rejestracja akwarysty' }}</h1>
    @include('auth._messages')
    <form method="POST" action="{{ route('register.create', $type) }}">
        @csrf
        <input type="hidden" name="form_token" value="{{ $formToken }}">
        <div style="position:absolute;left:-9999px" aria-hidden="true">
            <label>Nie wypełniaj tego pola <input type="text" name="fax" tabindex="-1" autocomplete="off"></label>
        </div>
        <p><label>Imię i nazwisko <input name="name" value="{{ old('name') }}" required></label></p>
        <p><label>E-mail <input type="email" name="email" value="{{ old('email') }}" required></label></p>
        <p><label>Hasło <input type="password" name="password" required autocomplete="new-password"></label></p>
        <p><label>Powtórz hasło <input type="password" name="password_confirmation" required autocomplete="new-password"></label></p>
        @if ($shop)
            <p><label>Nazwa firmy <input name="company_name" value="{{ old('company_name') }}" required></label></p>
            <p><label>NIP <input name="nip" value="{{ old('nip') }}" required></label></p>
            <p><label>Adres <input name="address" value="{{ old('address') }}" required></label></p>
            <p><label>Telefon <input type="tel" name="phone" value="{{ old('phone') }}" required></label></p>
        @endif
        <p><label><input type="checkbox" name="terms" value="1" required> Akceptuję regulamin</label></p>
        <p><label><input type="checkbox" name="privacy" value="1" required> Zapoznałem(-am) się z polityką prywatności</label></p>
        <p><label><input type="checkbox" name="adult" value="1" required> Oświadczam, że jestem pełnoletni(-a)</label></p>
        <p><label><input type="checkbox" name="newsletter" value="1"> Chcę otrzymywać informacje e-mail (opcjonalnie, można wycofać w każdej chwili)</label></p>
        <p><button type="submit">Załóż konto</button></p>
    </form>
@endsection

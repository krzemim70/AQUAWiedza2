@extends('layouts.public')

@section('title', 'Moje konto — Aikwarium')

@section('content')
<div class="auth-card">
    <h1>Moje konto</h1>
    @include('auth._messages')
    <p>E-mail: {{ $user->email }}</p>
    <p>Poziom konta: {{ $tier->label() }}@if ($tier === \App\Domain\IdentityAccess\Models\Tier::Fishroom) (do {{ $user->fishroom_ends_at->timezone(config('app.display_timezone'))->format('d.m.Y') }})@endif</p>
    @if ($shops->isNotEmpty())
        <p>Sklepy: {{ $shops->pluck('name')->join(', ') }}</p>
    @endif
    <p><x-button variant="secondary" :href="route('account.privacy')">Prywatność</x-button></p>

    <h2>Nazwa</h2>
    <form method="POST" action="{{ route('account.name') }}">
        @csrf
        <p><label>Nazwa <input type="text" name="name" value="{{ old('name', $user->name) }}" required></label></p>
        <p><x-button type="submit">Zapisz nazwę</x-button></p>
    </form>

    <h2>Miasto</h2>
    <form method="POST" action="{{ route('account.city') }}">
        @csrf
        <p>
            <label for="account-city">Miasto (opcjonalnie)</label><br>
            <input id="account-city" type="text" name="city" value="{{ old('city', $user->city) }}" maxlength="100" autocomplete="address-level2" aria-describedby="account-city-hint">
        </p>
        <p id="account-city-hint"><small>Używamy go wyłącznie do pokazania ofert sklepów z Twojej okolicy. Możesz je zostawić puste lub usunąć w dowolnej chwili.</small></p>
        <p><x-button type="submit">Zapisz miasto</x-button></p>
    </form>

    <h2>Hasło</h2>
    <form method="POST" action="{{ route('account.password') }}">
        @csrf
        <p><label>Obecne hasło <input type="password" name="current_password" required autocomplete="current-password"></label></p>
        <p><label>Nowe hasło <input type="password" name="password" required autocomplete="new-password"></label></p>
        <p><label>Powtórz nowe hasło <input type="password" name="password_confirmation" required autocomplete="new-password"></label></p>
        <p><x-button type="submit">Zmień hasło</x-button></p>
    </form>
</div>
@endsection

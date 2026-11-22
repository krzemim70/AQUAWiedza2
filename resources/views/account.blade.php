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

    <h2>Nazwa</h2>
    <form method="POST" action="{{ route('account.name') }}">
        @csrf
        <p><label>Nazwa <input type="text" name="name" value="{{ old('name', $user->name) }}" required></label></p>
        <p><x-button type="submit">Zapisz nazwę</x-button></p>
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

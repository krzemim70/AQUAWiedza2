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
    <div class="flex flex-wrap gap-2">
        <x-button variant="secondary" :href="route('account.privacy')">Prywatność</x-button>
        @if ($canWatch)
            <x-button variant="secondary" :href="route('watches.index')">Obserwowane rekordy</x-button>
        @endif
    </div>

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
        <p id="account-city-hint"><small>Do powiadomień o dostępności używamy aktualnego miasta z konta. Przy pustym polu sprawdzamy oferty z całej Polski.</small></p>
        <p><x-button type="submit">Zapisz miasto</x-button></p>
    </form>

    @if ($canPush)
        <section class="account-push" aria-labelledby="push-heading">
            <h2 id="push-heading">Powiadomienia push</h2>
            <p id="push-hint">Otrzymuj w przeglądarce codzienne przypomnienia o terminach w akwarium.</p>
            @if (! $pushGlobalEnabled)
                <p>Powiadomienia push są obecnie wyłączone w ustawieniach serwisu.</p>
            @elseif ($vapidPublicKey === '')
                <p>Powiadomienia push nie są jeszcze skonfigurowane na serwerze.</p>
            @else
                <div
                    data-push-widget
                    data-vapid-key="{{ $vapidPublicKey }}"
                    data-subscribe-url="{{ route('push.store') }}"
                    data-unsubscribe-url="{{ route('push.destroy') }}"
                    data-csrf="{{ csrf_token() }}"
                    data-enabled="{{ $pushEnabled ? '1' : '0' }}"
                >
                    <p data-push-nojs>Włączenie powiadomień push wymaga włączonej obsługi JavaScript w przeglądarce.</p>
                    <div data-push-controls hidden>
                        <button
                            type="button"
                            role="switch"
                            id="push-toggle"
                            class="push-switch"
                            aria-checked="{{ $pushEnabled && $pushSubscriptionCount > 0 ? 'true' : 'false' }}"
                            aria-describedby="push-hint"
                            data-push-toggle
                        >
                            <span class="push-switch-track" aria-hidden="true"><span class="push-switch-thumb"></span></span>
                            <span data-push-label>
                                {{ $pushEnabled && $pushSubscriptionCount > 0 ? 'Powiadomienia push: włączone' : 'Powiadomienia push: wyłączone' }}
                            </span>
                        </button>
                        <p class="push-status" role="status" aria-live="polite" data-push-status></p>
                    </div>
                </div>
            @endif
            <details class="advisor-details push-ios-guide">
                <summary>Instrukcja dla iPhone’a i iPada (iOS)</summary>
                <p>Na systemie iOS powiadomienia push działają po dodaniu Aikwarium do ekranu początkowego:</p>
                <ol class="push-ios-steps">
                    <li>Otwórz Aikwarium w przeglądarce Safari.</li>
                    <li>Stuknij przycisk „Udostępnij” na pasku Safari.</li>
                    <li>Wybierz „Dodaj do ekranu początkowego” i potwierdź.</li>
                    <li>Otwórz Aikwarium z ikony na ekranie początkowym, wejdź w „Moje konto” i włącz przełącznik powiadomień push.</li>
                </ol>
            </details>
        </section>
    @endif

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

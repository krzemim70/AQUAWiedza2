@extends('layouts.public')

@section('title', 'Prywatność — Moje konto')

@section('content')
    <div class="mx-auto max-w-3xl">
        <div class="page-hero">
            <x-button variant="secondary" :href="route('account')" class="mb-5">
                <x-heroicon-o-arrow-left class="size-4" aria-hidden="true" /> Moje konto
            </x-button>
            <h1>Prywatność</h1>
        </div>

        <div aria-live="polite">
            @if (session('status'))
                <x-alert type="success" role="status">{{ session('status') }}</x-alert>
            @endif
        </div>

        <section aria-labelledby="consent-title">
            <x-card>
                <h2 id="consent-title">Historia zgód</h2>
                <p>Cofnięcie obowiązkowych potwierdzeń oznacza usunięcie konta.</p>
                @if ($consents->isEmpty())
                    <p>Brak zapisanych decyzji.</p>
                @else
                    @php($labels = ['terms' => 'Regulamin', 'privacy' => 'Polityka prywatności', 'adult' => 'Deklaracja pełnoletności', 'newsletter' => 'Newsletter'])
                    <ul class="mb-5 divide-y divide-line border-y border-line">
                        @foreach ($consents as $consent)
                            <li class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1 py-3">
                                <span><strong>{{ $labels[$consent->document] ?? $consent->document }}</strong> · wersja {{ $consent->document_version }}</span>
                                <span class="text-sm text-muted">
                                    @if ($consent->granted)
                                        Udzielono
                                    @elseif ($withdrawn->has($consent->id))
                                        Wycofano
                                    @else
                                        Brak zgody
                                    @endif
                                    ·
                                    <time datetime="{{ $consent->created_at->toIso8601String() }}">{{ $consent->created_at->timezone(config('app.display_timezone'))->format('d.m.Y, H:i') }}</time>
                                </span>
                            </li>
                        @endforeach
                    </ul>
                @endif
                @if ($newsletterGranted)
                    <form method="POST" action="{{ route('account.privacy.newsletter') }}">
                        @csrf
                        <x-button type="submit" variant="secondary">Cofnij zgodę na newsletter</x-button>
                    </form>
                @endif
            </x-card>
        </section>

        <section aria-labelledby="export-title">
            <x-card>
                <h2 id="export-title">Eksport danych</h2>
                <p id="export-description">Plik JSON zawiera dane konta, zgody, akwaria, koszyk i zamówienia. Możesz go pobrać raz na {{ $exportInterval }} min.</p>
                <form method="POST" action="{{ route('account.privacy.export') }}">
                    @csrf
                    <x-button type="submit" aria-describedby="export-description">
                        <x-heroicon-o-arrow-down-tray class="size-5" aria-hidden="true" /> Pobierz moje dane
                    </x-button>
                </form>
            </x-card>
        </section>

        <section aria-labelledby="delete-title">
            <x-card>
                <h2 id="delete-title">Usunięcie konta</h2>
                @if ($deletion->allowed)
                    <p id="delete-description">Ta operacja jest nieodwracalna. Usuniemy dane konta, akwaria i koszyk. Złożone zamówienia pozostaną w sklepach po usunięciu danych kontaktowych.</p>
                    @error('deletion')
                        <x-alert type="error" role="alert">{{ $message }}</x-alert>
                    @enderror
                    <form method="POST" action="{{ route('account.privacy.delete') }}" aria-describedby="delete-description" class="max-w-md">
                        @csrf
                        @method('DELETE')
                        <div class="field">
                            <label for="delete-password">Obecne hasło</label>
                            <input id="delete-password" class="scroll-mt-28 ring-1 ring-muted focus-visible:ring-2 focus-visible:ring-focus" type="password" name="current_password" autocomplete="current-password" required
                                @error('current_password') aria-invalid="true" aria-describedby="delete-password-error" autofocus @enderror>
                            @error('current_password')
                                <span id="delete-password-error" role="alert" class="block">{{ $message }}</span>
                            @enderror
                        </div>
                        <div class="field">
                            <label for="delete-confirmation">Wpisz słowo USUŃ</label>
                            <input id="delete-confirmation" class="scroll-mt-28 ring-1 ring-muted focus-visible:ring-2 focus-visible:ring-focus" type="text" name="confirmation" autocomplete="off" spellcheck="false" required
                                @error('confirmation') aria-invalid="true" aria-describedby="delete-confirmation-error" @if (! $errors->has('current_password')) autofocus @endif @enderror>
                            @error('confirmation')
                                <span id="delete-confirmation-error" role="alert" class="block">{{ $message }}</span>
                            @enderror
                        </div>
                        <x-button type="submit">Usuń konto</x-button>
                    </form>
                @else
                    <p>{{ $deletion->message }}</p>
                @endif
            </x-card>
        </section>
    </div>
@endsection

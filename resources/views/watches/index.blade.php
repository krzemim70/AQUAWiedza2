@extends('layouts.public')

@section('title', 'Obserwowane rekordy — Aikwarium')

@section('content')
<div class="mx-auto max-w-3xl space-y-6">
    <header class="page-hero">
        <x-button variant="ghost" :href="route('account')">
            <x-heroicon-o-arrow-left class="size-5" aria-hidden="true" />
            Moje konto
        </x-button>
        <h1>Obserwowane rekordy</h1>
    </header>

    @include('auth._messages')

    <x-card>
        <h2>E-maile o dostępności</h2>
        <p id="availability-hint">Przy każdym sprawdzeniu ofert używamy aktualnego miasta z Twojego konta. Przy pustym polu sprawdzamy oferty z całej Polski.</p>
        <form method="POST" action="{{ route('watches.preference') }}" class="flex flex-wrap items-center gap-3">
            @csrf
            <input type="hidden" name="availability_email" value="0">
            <label class="mb-0 flex min-h-11 cursor-pointer items-center gap-2">
                <input type="checkbox" name="availability_email" value="1" class="focus-visible:outline-focus!" @checked($emailEnabled) aria-describedby="availability-hint">
                Otrzymuj e-maile o dostępności
            </label>
            <x-button type="submit" variant="secondary">Zapisz ustawienie</x-button>
        </form>
    </x-card>

    <x-card>
        <h2>Twoje obserwacje</h2>
        @if ($watches->isEmpty())
            <p>Na tej stronie nie ma obserwacji.</p>
        @else
            <ul class="m-0 list-none divide-y divide-line p-0">
                @foreach ($watches as ['watch' => $watch, 'name' => $name])
                    <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                        <div class="min-w-0">
                            @if ($name !== null)
                                <a href="{{ route('catalog.show', [$watch->area->value, $watch->legacy_id]) }}" class="inline-flex min-h-11 items-center font-semibold">{{ $name }}</a>
                            @else
                                <p class="font-semibold">{{ $watch->legacy_id }} · brak rekordu w katalogu</p>
                            @endif
                            <p class="text-sm text-muted">{{ $watch->notified_at !== null ? 'Powiadomienie przekazano do wysyłki' : 'Oczekuje na dostępną ofertę' }}</p>
                        </div>
                        <form method="POST" action="{{ route('watches.destroy', $watch->id) }}">
                            @csrf
                            @method('DELETE')
                            <x-button type="submit" variant="secondary" :aria-label="'Zakończ obserwację: '.($name ?? $watch->legacy_id)">Zakończ obserwację</x-button>
                        </form>
                    </li>
                @endforeach
            </ul>
        @endif
        @if ($watches->hasPages())
            <nav aria-label="Strony obserwowanych rekordów" class="mt-4 flex flex-wrap gap-2">
                @if ($watches->previousPageUrl())
                    <x-button variant="secondary" :href="$watches->previousPageUrl()">Poprzednia strona</x-button>
                @endif
                @if ($watches->nextPageUrl())
                    <x-button variant="secondary" :href="$watches->nextPageUrl()">Następna strona</x-button>
                @endif
            </nav>
        @endif
    </x-card>
</div>
@endsection

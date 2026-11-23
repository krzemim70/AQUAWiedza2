@if (session('watch_error') === [$area->value, $id] && $errors->getBag('recordWatch')->has('watch'))
    <x-alert type="error" role="alert">{{ $errors->getBag('recordWatch')->first('watch') }}</x-alert>
@endif

@if (! $guest && $canStartWatch && $availableOffer !== null)
    <p class="mt-3 text-muted">Brak dostępnych ofert w mieście z Twojego konta.</p>
@endif

@if ($watchPending)
    <p class="mt-3" @if (session('watch_saved') === [$area->value, $id]) role="status" aria-live="polite" @endif>
        Obserwujesz ten rekord. {{ $watchEmailEnabled ? 'Wyślemy e-mail, gdy pojawi się dostępna oferta.' : 'E-maile o dostępności są wyłączone.' }}
    </p>
    <x-button :href="route('watches.index')" variant="ghost">Moje obserwacje</x-button>
@elseif ($canStartWatch)
    @if ($guest)
        <x-button :href="route('register.create', 'akwarysta')" variant="secondary" class="mt-3">
            <x-heroicon-o-bell class="size-5" aria-hidden="true" />
            Załóż konto, by otrzymać powiadomienie
        </x-button>
    @else
        <form method="POST" action="{{ route('watches.store', [$area->value, $id]) }}" class="mt-3">
            @csrf
            <input type="hidden" name="return_to" value="{{ $returnTo }}">
            <x-button type="submit" variant="secondary" :aria-label="'Daj znać, gdy będzie dostępne: '.$name">
                <x-heroicon-o-bell class="size-5" aria-hidden="true" />
                Daj znać, gdy będzie dostępne
            </x-button>
        </form>
    @endif
@endif

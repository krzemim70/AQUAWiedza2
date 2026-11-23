<div>
    <x-card class="mb-6">
        <h3>Ostatni pomiar</h3>
        @if ($latest)
            <p>
                Wykonany:
                <time datetime="{{ $latest->measured_at->toIso8601String() }}">{{ $latest->measured_at->timezone(config('app.display_timezone'))->format('d.m.Y H:i') }}</time>
            </p>
            <dl>
                @foreach ($labels as $param => $label)
                    @if ($latest->$param !== null)
                        <dt>{{ $label }}</dt>
                        <dd>{{ $latest->$param }}</dd>
                    @endif
                @endforeach
            </dl>
        @else
            <p>Brak pomiarów. Dodaj pierwszy poniżej.</p>
        @endif
        <div aria-live="polite">
            @if ($waterReport)
                @if ($waterReport->stale)
                    <p>Wiek pomiaru (dni): {{ $waterReport->ageInDays }}.</p>
                @endif
                @if ($waterReport->hints)
                    <h4>Porównanie pomiaru z katalogiem</h4>
                    <ul class="m-0 list-none space-y-4 p-0">
                        @foreach ($waterReport->hints as $hint)
                            <li>
                                <p>{{ $hint->message }}</p>
                                <x-why :evidence="$hint->evidence" />
                            </li>
                        @endforeach
                    </ul>
                    @if ($canReviewStock && collect($waterReport->hints)->contains('reviewStock', true))
                        <x-button variant="secondary" class="mt-4" :href="route('aquarium.tank', ['tank' => $tankId, 'karta' => 'obsada']).'#compat-heading'">
                            Zgodność obsady
                        </x-button>
                    @endif
                @endif
            @endif
        </div>
    </x-card>

    <h3>Dodaj pomiar</h3>
    @if ($saved)
        <p role="status">Pomiar zapisany.</p>
    @endif
    @foreach ($warnings as $warning)
        <p role="alert">Uwaga: {{ $warning }}</p>
    @endforeach

    <form wire:submit="save">
        @error('values')
            <p role="alert">{{ $message }}</p>
        @enderror

        <x-field name="measured_at" type="datetime-local" label="Data i godzina pomiaru" required />
        @foreach ($labels as $param => $label)
            <x-field :name="$param" :label="$label" type="text" inputmode="decimal" />
        @endforeach
        <p>Wypełnij co najmniej jeden parametr; puste pola oznaczają brak odczytu.</p>

        <button type="submit" wire:loading.attr="disabled">Zapisz pomiar</button>
    </form>
</div>

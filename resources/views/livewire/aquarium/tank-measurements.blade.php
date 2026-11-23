@php
    $tz = config('app.display_timezone');
    $flashStatus = session('measurement_status');
    $flashWarnings = is_array(session('measurement_warnings')) ? session('measurement_warnings') : [];
@endphp

<div class="min-w-0 space-y-6">
    @if ($flashStatus || $saved)
        <p role="status">{{ $flashStatus ?: 'Pomiar zapisany.' }}</p>
    @endif
    @foreach (array_merge($flashWarnings, $warnings) as $warning)
        <p role="alert">Uwaga: {{ $warning }}</p>
    @endforeach

    <x-card class="mb-6">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h3 class="!mb-0">Ostatni pomiar</h3>
            @if ($canQuickMeasure)
                <x-button variant="secondary" :href="route('aquarium.quick-measurement', ['zbiornik' => $tankId])">
                    Szybki pomiar
                </x-button>
            @endif
        </div>
        @if ($latest)
            <p class="mt-3">
                Wykonany:
                <time datetime="{{ $latest->measured_at->toIso8601String() }}">{{ $latest->measured_at->timezone($tz)->format('d.m.Y H:i') }}</time>@if ($latest->method) · {{ $latest->method->label() }}@endif
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
            <p class="mt-3">Brak pomiarów. Dodaj pierwszy poniżej.</p>
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
                                @php
                                    $product = $waterReport->productsByParameter[$hint->parameter] ?? null;
                                    $blockReason = $waterReport->productBlockReasonsByParameter[$hint->parameter] ?? null;
                                @endphp
                                @if ($product && $hint->parameter === 'ph' && $hint->productDirection === 'down')
                                    <p>pH {{ str_replace('.', ',', (string) (float) $waterReport->measurement->ph) }} — powyżej zakresu podanego w katalogu dla wszystkich gatunków w zbiorniku.</p>
                                @else
                                    <p>{{ $hint->message }}</p>
                                @endif
                                <x-why :evidence="$hint->evidence" />
                                @if ($product)
                                    @php $productRoute = ['area' => 'care', 'id' => $product->evidence->recordId]; @endphp
                                    <x-card class="mt-3">
                                        <h5 class="mb-2 font-semibold">Opcja z katalogu — {{ $product->name }}</h5>
                                        <figure class="mb-4">
                                            <figcaption class="text-sm text-muted">
                                                @if ($product->sourceLabel)
                                                    Źródło: {{ $product->sourceLabel }}@if ($product->sourceRef) ({{ $product->sourceRef }})@endif
                                                @else
                                                    Opis i warunki użycia z katalogu
                                                @endif
                                            </figcaption>
                                            <blockquote>„{{ $product->evidence->value }}”</blockquote>
                                        </figure>
                                        <p>Przed użyciem sprawdź instrukcję producenta.</p>
                                        <x-why :evidence="[$product->evidence]" />
                                        <div class="mt-3 flex flex-wrap gap-2">
                                            <x-button variant="secondary" :href="route('catalog.show', $productRoute)">Strona produktu</x-button>
                                            <x-button variant="secondary" :href="route('catalog.show', $productRoute + ['oferty' => 'okolica']).'#oferty'">Oferty w okolicy</x-button>
                                        </div>
                                    </x-card>
                                @elseif ($blockReason)
                                    <p class="text-sm text-muted">Brak podpowiedzi preparatu — {{ $blockReason }}.</p>
                                @endif
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

    @if ($recent->isNotEmpty())
        <x-card class="min-w-0">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <h3 class="!mb-0">Ostatnie 3 odczyty</h3>
                @if ($canHistory)
                    <a class="inline-flex min-h-11 items-center text-sm" href="{{ route('aquarium.tank', ['tank' => $tankId, 'karta' => 'historia']) }}">
                        Historia i trendy &rarr;
                    </a>
                @endif
            </div>
            <ul class="m-0 mt-3 list-none space-y-3 p-0">
                @foreach ($recent as $row)
                    @php $m = $row['m']; @endphp
                    <li class="rounded-xl border border-line p-3">
                        <div class="flex flex-wrap items-baseline justify-between gap-2 text-sm">
                            <strong>
                                <time datetime="{{ $m->measured_at->toIso8601String() }}">{{ $m->measured_at->timezone($tz)->format('d.m.Y H:i') }}</time>
                            </strong>
                            @if ($m->method)
                                <span class="text-muted">{{ $m->method->label() }}</span>
                            @endif
                        </div>
                        <p class="!mb-0 mt-1 text-sm">
                            @php
                                $parts = [];
                                foreach ($labels as $param => $label) {
                                    if ($m->$param !== null) {
                                        $parts[] = $label.': '.str_replace('.', ',', $m->$param);
                                    }
                                }
                            @endphp
                            {{ implode(' · ', $parts) }}
                        </p>
                        @if ($row['warnings'] !== [])
                            <p class="!mb-0 mt-1 text-xs text-danger">{{ implode(' ', $row['warnings']) }}</p>
                        @endif
                    </li>
                @endforeach
            </ul>
        </x-card>
    @endif

    <h3>Dodaj pomiar</h3>

    <form wire:submit="save">
        @error('values')
            <p role="alert">{{ $message }}</p>
        @enderror

        <x-field name="measured_at" type="datetime-local" label="Data i godzina pomiaru" required />
        <x-field name="method" label="Metoda pomiaru (opcjonalnie)" as="select">
            <option value="">— nie podawaj —</option>
            @foreach ($methods as $item)
                <option value="{{ $item->value }}">{{ $item->label() }}</option>
            @endforeach
        </x-field>
        @foreach ($labels as $param => $label)
            <x-field :name="$param" :label="$label" type="text" inputmode="decimal" />
        @endforeach
        <p>Wypełnij co najmniej jeden parametr; puste pola oznaczają brak odczytu.</p>

        <button type="submit" wire:loading.attr="disabled">Zapisz pomiar</button>
    </form>
</div>

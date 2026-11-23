@php
    $tz = config('app.display_timezone');
@endphp

@component('layouts.aquarist', ['title' => 'Szybki pomiar — Aikwarium'])
<div class="min-w-0 space-y-6">
    <p class="flex flex-wrap items-center gap-4 text-sm">
        <a href="{{ route('aquarium.index') }}">&larr; Moje akwarium</a>
        @if ($selectedTank !== null)
            <a href="{{ route('aquarium.tank', ['tank' => $selectedTank->id, 'karta' => 'pomiary']) }}">Pomiary zbiornika</a>
        @endif
    </p>

    <h1>Szybki pomiar</h1>
    <p class="lead">Wpisz tylko te parametry, które zmierzyłeś. Puste pola oznaczają brak odczytu.</p>

    @if ($selectedTank === null)
        <x-card class="empty-state">
            <x-heroicon-o-beaker aria-hidden="true" />
            <p>Nie masz jeszcze żadnego zbiornika, do którego można zapisać pomiar.</p>
            <x-button :href="route('aquarium.index')">Dodaj zbiornik</x-button>
        </x-card>
    @else
        @if ($tanks->count() > 1)
            <nav aria-label="Wybór zbiornika" class="min-w-0">
                <p class="mb-2 text-sm font-semibold text-text-2">Zbiornik</p>
                <ul class="m-0 flex flex-wrap gap-2 list-none p-0">
                    @foreach ($tanks as $item)
                        @php $isCurrent = $item->id === $selectedTank->id; @endphp
                        <li>
                            <a href="{{ route('aquarium.quick-measurement', ['zbiornik' => $item->id]) }}"
                                @class(['button', 'button-secondary' => ! $isCurrent])
                                @if ($isCurrent) aria-current="page" @endif>
                                {{ $item->name }}
                            </a>
                        </li>
                    @endforeach
                </ul>
            </nav>
        @else
            <p class="text-sm text-muted">Zbiornik: <strong>{{ $selectedTank->name }}</strong></p>
        @endif

        @if ($errors->any())
            <div class="alert alert-error" role="alert" tabindex="-1">
                <p class="font-semibold">Sprawdź formularz przed zapisem:</p>
                <ul class="m-0 list-disc pl-5">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <form method="POST" action="{{ route('aquarium.quick-measurement.store') }}" class="card min-w-0 space-y-6 !max-w-none">
            @csrf
            <input type="hidden" name="tank_id" value="{{ $selectedTank->id }}">
            <input type="hidden" name="idempotency_key" value="{{ $idempotencyKey }}">

            <fieldset class="min-w-0">
                <legend class="mb-3 text-base font-semibold">Metoda pomiaru (opcjonalnie)</legend>
                @php $selectedMethod = old('method', ''); @endphp
                <div class="grid grid-cols-1 gap-2 min-[300px]:grid-cols-2 sm:grid-cols-5">
                    <label class="!m-0 flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-line px-3 py-2 has-[:checked]:border-aqua has-[:checked]:bg-aqua-50">
                        <input type="radio" name="method" value="" class="shrink-0" @checked($selectedMethod === '')>
                        <span class="min-w-0 wrap-break-word text-sm">Nie podawaj</span>
                    </label>
                    @foreach ($methods as $item)
                        <label class="!m-0 flex min-h-11 cursor-pointer items-center gap-2 rounded-xl border border-line px-3 py-2 has-[:checked]:border-aqua has-[:checked]:bg-aqua-50">
                            <input type="radio" name="method" value="{{ $item->value }}" class="shrink-0" @checked($selectedMethod === $item->value)>
                            <span class="min-w-0 wrap-break-word text-sm">{{ $item->label() }}</span>
                        </label>
                    @endforeach
                </div>
                @error('method')
                    <p role="alert" class="field-error">{{ $message }}</p>
                @enderror
            </fieldset>

            <fieldset class="min-w-0">
                <legend class="mb-3 text-base font-semibold">Parametry wody</legend>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    @foreach ($labels as $param => $label)
                        @php
                            $id = 'qm-'.$param;
                            $last = $lastValues[$param] ?? null;
                            $hasError = $errors->has($param);
                            $oldVal = old("values.$param");
                            $describedBy = $id.'-last'.($hasError ? ' '.$id.'-error' : '');
                        @endphp
                        <div class="field !m-0 min-w-0">
                            <label for="{{ $id }}">{{ $label }}</label>
                            <input id="{{ $id }}"
                                name="values[{{ $param }}]"
                                type="text"
                                inputmode="decimal"
                                autocomplete="off"
                                class="min-h-12 w-full text-lg"
                                value="{{ is_scalar($oldVal) ? $oldVal : '' }}"
                                aria-describedby="{{ $describedBy }}"
                                @if ($hasError) aria-invalid="true" @endif>
                            <small id="{{ $id }}-last">
                                @if ($last !== null)
                                    Ostatnio: {{ str_replace('.', ',', $last['value']) }}
                                    ({{ $last['measured_at']->timezone($tz)->format('d.m.Y') }})
                                @else
                                    Ostatnio: brak odczytu
                                @endif
                            </small>
                            @error($param)
                                <p id="{{ $id }}-error" role="alert" class="field-error">{{ $message }}</p>
                            @enderror
                        </div>
                    @endforeach
                </div>
            </fieldset>

            <div class="field !m-0 max-w-xs min-w-0">
                <label for="qm-measured-at">Data i godzina pomiaru</label>
                <input id="qm-measured-at"
                    name="measured_at"
                    type="datetime-local"
                    class="min-h-11 w-full"
                    value="{{ is_scalar(old('measured_at')) ? old('measured_at') : $defaultMeasuredAt }}"
                    @error('measured_at') aria-invalid="true" aria-describedby="qm-measured-at-error" @enderror>
                @error('measured_at')
                    <p id="qm-measured-at-error" role="alert" class="field-error">{{ $message }}</p>
                @enderror
            </div>

            <div class="flex flex-wrap gap-3">
                <x-button type="submit" size="lg" class="w-full sm:w-auto">Zapisz pomiar</x-button>
            </div>
        </form>
    @endif
</div>
@endcomponent

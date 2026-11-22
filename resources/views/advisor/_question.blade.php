@php
    $topic = $step->context ?? ($profile[0]['answer'] ?? '');
    $heading = $compact ? 'h1' : 'h2';
@endphp

<header @class(['mb-4 flex items-center gap-4', 'mt-8' => ! $compact])>
    @if ($compact)
        <x-button variant="secondary" class="shrink-0" :href="$backUrl"><x-heroicon-o-arrow-left class="icon" aria-hidden="true" /> Wstecz</x-button>
    @endif
    <div class="min-w-0 flex-1">
        @if ($compact)
            <p class="!mb-1 text-sm font-semibold text-text-2">{{ $topic }}</p>
        @endif
        <label for="advisor-progress" @class(['sr-only' => $compact])>Postęp doboru</label>
        <progress id="advisor-progress" class="block h-2 w-full accent-aqua" value="{{ $step->progress }}" max="100">{{ $step->progress }}%</progress>
    </div>
</header>

@if ($step->intro || $carryingTank)
    <div class="advisor-feedback" aria-live="polite">
        @if ($step->intro)
            <p>{{ $step->intro }}</p>
        @endif
        @if ($carryingTank)
            <p>Użyjemy poprzedniego litrażu i typu akwarium, jeśli pasują do wybranego obszaru.</p>
        @endif
    </div>
@endif

<x-card>
    @if ($step->search)
        <form method="GET" action="{{ route('advisor.guided') }}">
            <input type="hidden" name="s" value="{{ $params['s'] }}">
            <fieldset>
                <legend><{{ $heading }} class="advisor-question-heading" id="advisor-step" tabindex="-1">{{ $step->text }}</{{ $heading }}></legend>
                <label for="advisor-search">Nazwa lub pełny identyfikator produktu</label>
                <div class="flex flex-col items-start gap-3 sm:flex-row sm:items-end">
                    <input id="advisor-search" type="search" name="q" value="{{ $phrase }}" minlength="{{ $searchMin }}" required aria-describedby="advisor-search-hint">
                    <x-button type="submit">Szukaj</x-button>
                </div>
                <small id="advisor-search-hint">Minimalna liczba znaków to {{ $searchMin }}.</small>
            </fieldset>
        </form>

        @if ($phrase !== '')
            <div class="mt-6 text-text-2" role="status">
                @if ($phraseTooShort)
                    Wpisz dłuższą nazwę lub pełny identyfikator.
                @elseif ($hits === [])
                    Brak wyników. Spróbuj innej nazwy lub pełnego identyfikatora.
                @else
                    Liczba znalezionych produktów: {{ count($hits) }}
                @endif
            </div>
        @endif

        @if ($hits !== [])
            <form class="mt-4" method="POST" action="{{ route('advisor.guided.answer', $params + ['q' => $phrase]) }}">
                @csrf
                <input type="hidden" name="key" value="{{ $step->key }}">
                <fieldset>
                    <legend>Wybierz produkt</legend>
                    <ul class="space-y-2">
                        @foreach ($hits as $hit)
                            <li>
                                <button class="advisor-option" type="submit" name="code" value="{{ $hit->area->value }}:{{ $hit->id }}">
                                    <span>{{ $hit->name }} <span class="block text-sm font-normal text-muted">{{ $hit->area->label() }} · {{ $hit->id }}</span></span>
                                    <x-heroicon-o-chevron-right class="size-5 shrink-0" aria-hidden="true" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                </fieldset>
            </form>
        @endif
    @else
        <form method="POST" action="{{ route('advisor.guided.answer', $params) }}">
            @csrf
            <input type="hidden" name="key" value="{{ $step->key }}">
            <fieldset>
                <legend><{{ $heading }} class="advisor-question-heading" id="advisor-step" tabindex="-1">{{ $step->text }}</{{ $heading }}></legend>
                @if ($step->options === [])
                    <p>Nie ma dostępnych odpowiedzi. Wróć do poprzedniego pytania i wybierz inny obszar.</p>
                @else
                    <ul class="space-y-2">
                        @foreach ($step->options as $option)
                            <li>
                                <button class="advisor-option" type="submit" name="code" value="{{ $option->code }}">
                                    {{ $option->label }} <x-heroicon-o-chevron-right class="size-5 shrink-0" aria-hidden="true" />
                                </button>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </fieldset>
        </form>
    @endif
</x-card>

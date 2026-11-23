<div>
    <h1>Moje akwarium</h1>

    <section aria-labelledby="tanks-heading">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 id="tanks-heading" class="!mb-0">Moje zbiorniki</h2>
            @if ($tanks->isNotEmpty() && $canQuickMeasure)
                <x-button :href="route('aquarium.quick-measurement')">Szybki pomiar</x-button>
            @endif
        </div>
        @forelse ($tanks as $tank)
            <article>
                <h3><a href="{{ route('aquarium.tank', $tank->id) }}">{{ $tank->name }}</a></h3>
                <p>{{ $tank->type->value }}, {{ $tank->size_band?->label() ?? 'bez wielkości' }}@if ($tank->size_band) (ok. {{ $tank->volumeLiters() }} l)@endif</p>
                <x-button variant="secondary" :href="route('aquarium.tank', ['tank' => $tank->id, 'karta' => 'wyposazenie'])"
                    :aria-label="'Wyposażenie zbiornika '.$tank->name">Wyposażenie</x-button>
            </article>
        @empty
            <p>Nie masz jeszcze żadnego zbiornika.</p>
        @endforelse
    </section>

    @if ($upcoming !== null)
        <section aria-labelledby="upcoming-heading">
            <h2 id="upcoming-heading">Najbliższe przypomnienia</h2>
            @forelse ($upcoming as $occurrence)
                <p>
                    <time datetime="{{ $occurrence->due_on->toDateString() }}">{{ $occurrence->due_on->format('d.m.Y') }}</time>
                    — {{ $occurrence->reminder->title() }}
                    (<a href="{{ route('aquarium.tank', ['tank' => $occurrence->reminder->tank_id, 'karta' => 'przypomnienia']) }}">{{ $occurrence->reminder->tank->name }}</a>)
                </p>
            @empty
                <p>Brak zaległych i nadchodzących przypomnień.</p>
            @endforelse
        </section>
    @endif

    @if ($canPlan)
        <section aria-labelledby="plans-heading">
            <h2 id="plans-heading">Plan nowego akwarium</h2>
            <p>Zaplanuj zbiornik, porównaj gatunki według katalogu i zobacz oferty sklepów z okolicy.</p>
            <div class="flex flex-wrap gap-3">
                <x-button :href="route('creator.show')">Otwórz interaktywny kreator</x-button>
                <x-button :href="route('plans.index')" variant="secondary">Twoje zapisane plany</x-button>
            </div>
        </section>
    @endif

    <section aria-labelledby="add-heading">
        <h2 id="add-heading">Dodaj zbiornik</h2>
        @if ($canAdd)
            <livewire:aquarium.tank-form />
        @else
            <p>{{ $addMessage }}</p>
        @endif
    </section>
</div>

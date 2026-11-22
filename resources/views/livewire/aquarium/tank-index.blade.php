<div>
    <h1>Moje akwarium</h1>

    <section aria-labelledby="tanks-heading">
        <h2 id="tanks-heading">Moje zbiorniki</h2>
        @forelse ($tanks as $tank)
            <article>
                <h3><a href="{{ route('aquarium.tank', $tank->id) }}">{{ $tank->name }}</a></h3>
                <p>{{ $tank->type->value }}, {{ $tank->size_band->value }} (ok. {{ $tank->volumeLiters() }} l)</p>
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

    <section aria-labelledby="add-heading">
        <h2 id="add-heading">Dodaj zbiornik</h2>
        @if ($canAdd)
            <livewire:aquarium.tank-form />
        @else
            <p>{{ $addMessage }}</p>
        @endif
    </section>
</div>

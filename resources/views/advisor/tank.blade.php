@extends('layouts.public')

@section('title', 'Doradca — Aikwarium')

@section('content')
    <h1>Doradca</h1>
    @include('auth._messages')

    @if ($tanks->isEmpty())
        <p role="status">Nie masz jeszcze zbiornika. <a href="{{ route('aquarium.index') }}">Dodaj zbiornik w Moim akwarium</a>, a Doradca dobierze do niego gatunki.</p>
    @else
        @if ($tanks->count() > 1)
            <nav aria-label="Twoje zbiorniki">
                <ul>
                    @foreach ($tanks as $item)
                        <li><a href="{{ route('advisor.tank', ['tank' => $item->id]) }}" @if ($item->is($tank)) aria-current="page" @endif>{{ $item->name }}</a></li>
                    @endforeach
                </ul>
            </nav>
        @endif
        <p>Zbiornik: {{ $tank->name }} ({{ $tank->type->value }}@if ($tank->size_band), {{ $tank->size_band->label() }}@endif). <a href="{{ route('aquarium.tank', $tank->id) }}">Edytuj zbiornik</a></p>
        @if ($tank->size_band === null)
            <p role="status">Ten zbiornik nie ma wielkości. <a href="{{ route('aquarium.tank', $tank->id) }}">Uzupełnij wielkość zbiornika</a>, aby dobrać gatunki.</p>
        @endif
    @endif

    @isset($ranking)
        <section class="advisor-results" aria-labelledby="result-heading">
            <h2 id="result-heading">Wynik</h2>

            @if ($ranking->top() === null)
                <p role="status">Brak dopasowań dla tego zbiornika. Zmień ustawienia zbiornika i spróbuj ponownie.</p>
            @else
                @php($top = $ranking->top())
                <p>
                    @if ($ranking->fishDerived)
                        Wielkość ryby dopasowana do zbiornika ({{ $ranking->fish->label() }}).
                    @else
                        Wielkość ryby: {{ $ranking->fish->label() }}.
                    @endif
                    Ocenionych gatunków: {{ count($ranking->matches) }}, pominiętych: {{ $ranking->excluded }}.
                </p>

                @if ($ranking->topCount === 1)
                    <div class="winner-card relative"><h3>
                        <a class="catalog-card-link inline-flex min-h-11 items-center after:absolute after:inset-0"
                            href="{{ route('catalog.show', ['fauna', $top->record->id, 'wroc' => $catalogBackTo]) }}">Polecany gatunek: {{ $top->record->name }} ({{ $top->percent() }}%)</a>
                    </h3>
                    <p class="latin-name">{{ $top->record->latinName }}</p>
                    @if ($photo = array_find($card?->photos ?? [], fn ($photo) => filled($photo['url'])))
                        <figure class="mb-4 max-w-sm">
                            <img class="aspect-[8/5] w-full rounded-xl bg-tint object-cover" src="{{ $photo['url'] }}"
                                alt="{{ $top->record->name }}" width="800" height="500" loading="lazy" decoding="async">
                            @if ($photo['author'] || $photo['licence'])
                                <figcaption class="mt-2 text-sm text-muted">Zdjęcie: {{ implode(' · ', array_filter([$photo['author'], $photo['licence']])) }}</figcaption>
                            @endif
                        </figure>
                    @endif
                    <div class="progress" role="progressbar" aria-label="Dopasowanie" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $top->percent() }}"><span style="width: {{ $top->percent() }}%"></span></div>
                    @if ($ranking->decidedBy)
                        <p>Kilka gatunków ma ten sam wynik. O wyborze decyduje {{ $ranking->decidedBy->label() }}.</p>
                    @endif
                    @include('advisor._card-actions', [
                        'area' => \App\Domain\KnowledgeBase\Models\Area::Fauna,
                        'id' => $top->record->id, 'name' => $top->record->name, 'variant' => 'full',
                        'cardEntry' => $card, 'cardOffers' => $offersById[$top->record->id] ?? [], 'preferredTank' => $tank->id,
                    ])
                    </div>
                @else
                    <h3>{{ $ranking->topCount }} równorzędnych gatunków z wynikiem {{ $top->percent() }}%</h3>
                    <ul class="result-list">
                        @foreach ($ranking->topGroup() as $match)
                            @include('advisor._tank-compact', ['choose' => true])
                        @endforeach
                    </ul>
                @endif

                @if ($card && $ranking->topCount > 1)
                    <article class="card" aria-labelledby="card-heading">
                        <h3 id="card-heading">{{ $card->name }}</h3>
                        <p>{{ $card->nameLatin }}</p>
                        @include('advisor._card-actions', [
                            'area' => $card->area, 'id' => $card->id, 'name' => $card->name, 'variant' => 'full',
                            'cardEntry' => $card, 'cardOffers' => $offersById[$card->id] ?? [], 'preferredTank' => $tank->id,
                        ])
                    </article>
                @endif

                @if (count($ranking->matches) > $ranking->topCount)
                    <h3>Pozostałe gatunki</h3>
                    <ol class="result-list" start="{{ $ranking->topCount + 1 }}">
                        @foreach (array_slice($ranking->matches, $ranking->topCount) as $match)
                            @include('advisor._tank-compact')
                        @endforeach
                    </ol>
                @endif
            @endif
        </section>
    @endisset
@endsection

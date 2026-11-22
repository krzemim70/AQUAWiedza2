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
                    <div class="winner-card"><h3>Polecany gatunek: {{ $top->record->name }} ({{ $top->percent() }}%)</h3>
                    <p class="latin-name">{{ $top->record->latinName }}</p>
                    <div class="progress" role="progressbar" aria-label="Dopasowanie" aria-valuemin="0" aria-valuemax="100" aria-valuenow="{{ $top->percent() }}"><span style="width: {{ $top->percent() }}%"></span></div>
                    @if ($ranking->decidedBy)
                        <p>Kilka gatunków ma ten sam wynik. Ten wygrywa na kryterium: {{ $ranking->decidedBy->label() }}.</p>
                    @endif
                    <form method="POST" action="{{ route('advisor.livestock') }}">
                        @csrf
                        <input type="hidden" name="tank" value="{{ $tank->id }}">
                        <input type="hidden" name="species" value="{{ $top->record->id }}">
                        <x-button type="submit">Dodaj do obsady</x-button>
                    </form>
                    </div>
                @else
                    <h3>{{ $ranking->topCount }} równorzędnych gatunków z wynikiem {{ $top->percent() }}%</h3>
                    <ul class="result-list">
                        @foreach ($ranking->topGroup() as $match)
                            <li>
                                {{ $match->record->name }}
                                <a href="{{ route('advisor.tank', ['tank' => $tank->id, 'card' => $match->record->id]) }}">Dowiedz się więcej</a>
                            </li>
                        @endforeach
                    </ul>
                @endif

                @if ($card)
                    <article class="card" aria-labelledby="card-heading">
                        <h3 id="card-heading">{{ $card->name }}</h3>
                        <p>{{ $card->nameLatin }}</p>
                        @if ($card->description)
                            <h4>Dowiedz się więcej</h4>
                            {!! nl2br(e($card->description)) !!}
                        @endif
                    </article>
                @endif

                @include('advisor._offers', ['offers' => $offers])

                @if (count($ranking->matches) > $ranking->topCount)
                    <h3>Pozostałe gatunki</h3>
                    <ol class="result-list" start="{{ $ranking->topCount + 1 }}">
                        @foreach (array_slice($ranking->matches, $ranking->topCount) as $match)
                            <li>{{ $match->record->name }} — {{ $match->percent() }}%</li>
                        @endforeach
                    </ol>
                @endif
            @endif
        </section>
    @endisset
@endsection

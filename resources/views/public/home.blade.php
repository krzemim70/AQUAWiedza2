@extends('layouts.public')

@section('title', 'Aikwarium — dobór ryb do akwarium')

@section('content')
    <div class="home-hero"><h1>Aikwarium</h1>

    @auth
        </div>
        <p>Masz konto. Dobór dla swojego zbiornika znajdziesz w widoku <a href="{{ route('advisor.tank') }}">Doradca</a>.</p>
    @else
        <h2>Dobierz ryby do swojego akwarium</h2>
        </div>

        @if (($state ?? null) === 'limit')
            <p role="alert">Wykorzystałeś bezpłatny dobór. <a href="{{ route('register') }}">Załóż bezpłatne konto</a>, aby korzystać z doboru bez ograniczeń.</p>
        @elseif (($state ?? null) === 'blocked')
            <p role="alert">Zbyt wiele prób. Spróbuj ponownie za chwilę.</p>
        @elseif (($state ?? null) === 'none')
            <p role="status">Nie znaleźliśmy gatunków pasujących do tych kryteriów. Zmień wybór i spróbuj ponownie.</p>
        @elseif (($state ?? null) === 'tie')
            @php($top = $ranking->top())
            <section class="result-card" aria-labelledby="result-heading">
                <h2 id="result-heading">{{ $ranking->topCount }} gatunków z wynikiem {{ $top->percent() }}%</h2>
                <p>Kilka gatunków pasuje do Twojego akwarium tak samo dobrze. Możesz zobaczyć jeden z nich jako przykład albo doprecyzować wybór w formularzu poniżej.</p>
                <form method="POST" action="{{ route('drop.run') }}">
                    @csrf
                    <input type="hidden" name="request_key" value="{{ $requestKey }}">
                    <input type="hidden" name="reveal" value="1">
                    @foreach (['size', 'fish', 'temperature', 'ph'] as $field)
                        <input type="hidden" name="{{ $field }}" value="{{ $values[$field] ?? '' }}">
                    @endforeach
                    <button type="submit">Pokaż przykład</button>
                </form>
                <p>Pozostałe wyniki: {{ count($ranking->matches) - 1 }}. <a href="{{ route('register') }}">Załóż bezpłatne konto</a>, aby zobaczyć pełną listę.</p>
            </section>
        @elseif (($state ?? null) === 'card')
            @php($top = $ranking->top())
            <section class="result-card" aria-labelledby="result-heading">
                <h2 id="result-heading">{{ $top->record->name }}</h2>
                <p>{{ $top->record->latinName }} — dopasowanie {{ $top->percent() }}%</p>
                @if ($ranking->topCount > 1)
                    <p>To jeden z {{ $ranking->topCount }} równorzędnych gatunków z wynikiem {{ $top->percent() }}%.</p>
                @endif
                @if ($ranking->fishAdjusted)
                    <p>Dla tego zbiornika nie ma ryb o wybranej wielkości. Pokazujemy najbliższą dostępną: {{ $ranking->fish->label() }}.</p>
                @endif
                @if (count($ranking->matches) > 1)
                    <p>Pozostałe wyniki: {{ count($ranking->matches) - 1 }}. <a href="{{ route('register') }}">Załóż bezpłatne konto</a>, aby zobaczyć pełną listę z nazwami.</p>
                @endif
            </section>
        @endif

        <form class="profile-form" method="POST" action="{{ route('drop.run') }}">
            @csrf
            <input type="hidden" name="request_key" value="{{ \Illuminate\Support\Str::uuid() }}">
            <fieldset>
                <legend>Twoje akwarium</legend>
                @include('advisor._profile', ['values' => $values ?? []])
            </fieldset>
            <button type="submit">{{ isset($state) ? 'Dobierz ponownie' : 'Dobierz' }}</button>
        </form>
    @endauth
@endsection

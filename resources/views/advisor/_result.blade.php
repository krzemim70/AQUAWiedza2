@php
    use App\Domain\Advisor\Engine\CardRole;
    use App\Domain\Advisor\Engine\GroupType;
    use App\Domain\Advisor\Engine\GuideArea;

    $isTie = in_array($result->type, [GroupType::PartialTie, GroupType::FullTie], true);
    $n = $result->ranked;
    $few = in_array($n % 10, [2, 3, 4], true) && ! in_array($n % 100, [12, 13, 14], true);
    $unit = $result->area === GuideArea::Fauna
        ? ($n === 1 ? 'gatunek' : ($few ? 'gatunki' : 'gatunków'))
        : ($n === 1 ? 'pozycję' : ($few ? 'pozycje' : 'pozycji'));
@endphp

<p class="lead">{{ $result->label }}</p>

@if ($result->type === GroupType::Empty)
    <x-card class="mt-6 max-w-2xl">
        <span class="icon-badge"><x-heroicon-o-magnifying-glass aria-hidden="true" /></span>
        <h2>Brak dopasowań</h2>
        <p>Wróć do ostatniego pytania i zmień odpowiedź. Możesz też wybrać inny obszar lub typ akwarium.</p>
        @if ($backUrl)
            <x-button :href="$backUrl"><x-heroicon-o-arrow-left class="icon" aria-hidden="true" /> Wstecz</x-button>
        @endif
    </x-card>
@else
    <p class="text-text-2">Doradca sprawdził {{ $n }} {{ $unit }} dla wybranego obszaru i warunków.</p>
    @if ($result->notice)
        <p class="advisor-feedback" role="status">{{ $result->notice }}</p>
    @endif

    @if ($result->insufficient)
        <section aria-labelledby="advisor-unscored">
            <h2 id="advisor-unscored">Propozycje do sprawdzenia</h2>
            <div class="advisor-grid">
                @foreach ($result->cards as $card)
                    @include('advisor._result-card', ['card' => $card, 'full' => false, 'unscored' => true])
                @endforeach
            </div>
        </section>
    @else
        @if ($isTie)
            <section aria-labelledby="advisor-tied">
                <h2 id="advisor-tied">Równorzędne propozycje</h2>
                <div class="advisor-grid">
                    @foreach ($result->cards as $card)
                        @if ($card->role === CardRole::Tie)
                            @include('advisor._result-card', ['card' => $card, 'full' => false])
                        @endif
                    @endforeach
                </div>
            </section>
        @else
            @foreach ($result->cards as $card)
                @if ($card->role === CardRole::Main)
                    @include('advisor._result-card', ['card' => $card, 'full' => true])
                @endif
            @endforeach
        @endif

        @if (array_find($result->cards, fn ($card) => $card->role === CardRole::Alternative))
            <section class="mt-10" aria-labelledby="advisor-alternatives">
                <h2 id="advisor-alternatives">Warto rozważyć także</h2>
                <div class="advisor-grid">
                    @foreach ($result->cards as $card)
                        @if ($card->role === CardRole::Alternative)
                            @include('advisor._result-card', ['card' => $card, 'full' => false])
                        @endif
                    @endforeach
                </div>
            </section>
        @endif
    @endif
@endif

@include('advisor._calculation', ['criteria' => $result->criteria])

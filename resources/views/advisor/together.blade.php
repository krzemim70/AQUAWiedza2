@extends('layouts.public')

@php
    use App\Domain\Advisor\Engine\Compatibility\Check;
    use App\Domain\KnowledgeBase\Models\Area;

    $selectedNames = array_map(fn ($entry) => $entry->name, $result->species);
    $pageTitle = count($selectedNames) >= $result->minSpecies
        ? 'Czy razem: '.implode(', ', array_slice($selectedNames, 0, 2)).(count($selectedNames) > 2 ? ' i '.(count($selectedNames) - 2).' inne' : '').' — Aikwarium'
        : 'Czy te ryby mogą być razem? — Aikwarium';
    $pageDescription = count($selectedNames) >= $result->minSpecies
        ? 'Porównanie wymagań katalogowych dla: '.implode(', ', $selectedNames).' — temperatura, pH, wielkość akwarium i temperament z dowodami z bazy wiedzy.'
        : 'Sprawdź w katalogu Aikwarium, czy wybrane gatunki ryb mogą być w jednym akwarium. Porównanie pasm temperatury, pH i wielkości zbiornika z dowodami.';

    $selectedSet = array_flip($result->selectedIds);
    $speciesById = array_column($result->species, null, 'id');
    $canAddMore = count($result->selectedIds) < $result->maxSpecies;

    $pairChecks = [Check::TemperaturePair, Check::PhPair];
    $publicTableChecks = [Check::TemperaturePair, Check::PhPair, Check::Temperament];
    $pairFindings = $result->report === null
        ? []
        : array_values(array_filter($result->report->findings, fn ($f) => in_array($f->check, $pairChecks, true)));
    $temperamentFindings = $result->report === null
        ? []
        : array_values(array_filter($result->report->findings, fn ($f) => $f->check === Check::Temperament));
    $publicRows = $result->report === null
        ? []
        : array_values(array_filter($result->report->rows, fn ($r) => in_array($r->check, $publicTableChecks, true)));
@endphp

@section('title', $pageTitle)
@section('description', $pageDescription)
@if ($hasQuery)
    @section('noindex', '1')
@endif
@section('main', 'page-shell advisor-shell')

@section('content')
    <nav aria-label="Ścieżka nawigacji" class="mb-6 text-sm text-muted">
        <ol class="m-0 flex list-none flex-wrap items-center gap-x-2 p-0">
            <li><a href="{{ route('home') }}" class="inline-flex min-h-11 items-center">Strona główna</a></li>
            <li><span aria-hidden="true">›</span> <a href="{{ route('advisor') }}" class="inline-flex min-h-11 items-center">Doradca</a></li>
            <li aria-current="page"><span aria-hidden="true">›</span> Czy te ryby mogą być razem?</li>
        </ol>
    </nav>

    <header class="page-hero">
        <p class="eyebrow">Porównanie katalogowe</p>
        <h1>Czy te ryby mogą być razem?</h1>
        <p class="lead">
            Wybierz od {{ $result->minSpecies }} do {{ $result->maxSpecies }} gatunków fauny z katalogu Aikwarium.
            Zestawiamy pasma temperatury, pH, wielkości zbiornika i etykiety temperamentu z dowodem pod „Dlaczego?”.
        </p>
    </header>

    <section class="card" aria-labelledby="species-picker-heading">
        <div class="flex flex-wrap items-baseline justify-between gap-2">
            <h2 id="species-picker-heading">Wybrane gatunki ({{ count($result->selectedIds) }} z {{ $result->minSpecies }}–{{ $result->maxSpecies }})</h2>
            @if ($result->selectedIds !== [])
                <a class="inline-flex min-h-11 items-center text-sm" href="{{ route('advisor.together') }}">Wyczyść wybór</a>
            @endif
        </div>

        <div aria-live="polite">
            @if ($result->tooMany)
                <x-alert type="error" role="alert">
                    Możesz porównać maksymalnie {{ $result->maxSpecies }} gatunków naraz (wybrano {{ count($result->selectedIds) }}). Usuń nadmiarowe pozycje z listy poniżej.
                </x-alert>
            @elseif ($result->tooFew)
                <x-alert type="info" role="status">
                    Wybierz co najmniej {{ $result->minSpecies }} gatunki z aktywnego katalogu, aby zobaczyć porównanie.
                </x-alert>
            @endif

            @if ($result->unknownIds !== [])
                <x-alert type="info" role="status">
                    Brak danych katalogowych dla: {{ implode(', ', $result->unknownIds) }}.
                </x-alert>
            @endif
        </div>

        @if ($result->selectedIds !== [])
            <ul class="m-0 mb-6 flex list-none flex-wrap gap-2 p-0" aria-label="Wybrane gatunki do porównania">
                @foreach ($result->selectedIds as $selectedId)
                    @php
                        $entry = $speciesById[$selectedId] ?? null;
                        $remaining = array_values(array_filter($result->selectedIds, fn (string $id) => $id !== $selectedId));
                        $removeParams = [];
                        if ($remaining !== []) {
                            $removeParams['gatunki'] = implode(',', $remaining);
                        }
                        if ($search !== '') {
                            $removeParams['szukaj'] = $search;
                        }
                        $removeUrl = route('advisor.together', $removeParams);
                        $label = $entry?->name ?? $selectedId;
                    @endphp
                    <li class="inline-flex flex-wrap items-center gap-2 rounded-full border border-line bg-tint py-1 pr-2 pl-4">
                        @if ($entry)
                            <a href="{{ route('catalog.show', ['fauna', $entry->id]) }}" class="inline-flex min-h-11 items-center text-sm">
                                {{ $entry->name }}
                            </a>
                        @else
                            <span class="text-sm text-muted">{{ $selectedId }} (spoza katalogu)</span>
                        @endif
                        <a href="{{ $removeUrl }}" class="inline-flex min-h-11 items-center rounded-full px-3 text-sm text-danger" aria-label="Usuń z porównania: {{ $label }}">
                            <x-heroicon-o-x-mark class="icon" aria-hidden="true" /> Usuń
                        </a>
                    </li>
                @endforeach
            </ul>
        @else
            <p class="text-text-2">Wyszukaj pierwszy gatunek poniżej, aby dodać go do porównania.</p>
        @endif

        <form method="GET" action="{{ route('advisor.together') }}" class="mt-4 flex flex-wrap items-end gap-3" role="search">
            @if ($result->selectedIds !== [])
                <input type="hidden" name="gatunki" value="{{ implode(',', $result->selectedIds) }}">
            @endif
            <div class="min-w-60 flex-1">
                <label for="together-search">Szukaj gatunku (nazwa polska, łacińska lub ID)</label>
                <input id="together-search" type="search" name="szukaj" value="{{ $search }}" autocomplete="off"
                    placeholder="np. Gupik, Neon, FN0001">
            </div>
            <x-button type="submit">Szukaj</x-button>
        </form>

        @if ($search !== '')
            <div class="mt-6" aria-live="polite">
                @if (mb_strlen($search) < $result->searchMinChars)
                    <p class="text-sm text-muted">Wpisz co najmniej {{ $result->searchMinChars }} znaki nazwy lub identyfikator rekordu.</p>
                @elseif ($result->searchResults === [])
                    <p class="text-sm text-muted">Brak gatunków fauny dla frazy „{{ $search }}”.</p>
                @else
                    <h3>Wyniki wyszukiwania ({{ count($result->searchResults) }})</h3>
                    <ul class="m-0 list-none divide-y divide-line p-0" aria-label="Wyniki wyszukiwania gatunków">
                        @foreach ($result->searchResults as $entry)
                            @php
                                $alreadySelected = isset($selectedSet[$entry->id]);
                                $nextIds = [...$result->selectedIds, $entry->id];
                                $addUrl = route('advisor.together', ['gatunki' => implode(',', $nextIds)]);
                            @endphp
                            <li class="flex flex-wrap items-center justify-between gap-3 py-3">
                                <div class="min-w-0">
                                    <a href="{{ route('catalog.show', ['fauna', $entry->id]) }}" class="inline-flex min-h-11 items-center">
                                        {{ $entry->name }}
                                    </a>
                                    @if ($entry->nameLatin)
                                        <span class="latin-name text-sm" lang="la">({{ $entry->nameLatin }})</span>
                                    @endif
                                    <span class="text-xs text-muted">{{ $entry->id }}</span>
                                </div>
                                @if ($alreadySelected)
                                    <span class="text-sm text-muted">Wybrany</span>
                                @elseif ($canAddMore)
                                    <x-button variant="secondary" :href="$addUrl" :aria-label="'Dodaj do porównania: '.$entry->name">Dodaj</x-button>
                                @else
                                    <span class="text-sm text-muted">Osiągnięto limit {{ $result->maxSpecies }} gatunków</span>
                                @endif
                            </li>
                        @endforeach
                    </ul>
                @endif
            </div>
        @endif
    </section>

    @if ($result->report !== null)
        @php($verdict = $result->report->verdict())
        <section class="card" aria-labelledby="together-result-heading">
            <h2 id="together-result-heading">Wynik porównania katalogowego</h2>
            <p role="status" class="text-lg font-semibold">{{ $verdict->label() }}</p>

            <div class="my-6 rounded-xl border border-line bg-tint p-4">
                <label for="together-share-url">Link do udostępnienia tego porównania</label>
                <div class="mt-2 flex flex-wrap items-center gap-2">
                    <input id="together-share-url" type="url" readonly value="{{ $shareUrl }}" class="!mt-0 min-w-60 flex-1 bg-white">
                    <x-button type="button" variant="secondary" hidden data-copy-link="together-share-url" data-copy-status="together-copy-status">
                        <x-heroicon-o-link class="icon" aria-hidden="true" /> Kopiuj link
                    </x-button>
                    <a href="{{ $shareUrl }}" class="inline-flex min-h-11 items-center px-2 text-sm">Otwórz link</a>
                </div>
                <p id="together-copy-status" class="!mb-0 mt-2 text-sm text-muted" aria-live="polite"></p>
            </div>

            <h3>Parametry katalogowe wybranych gatunków</h3>
            <div class="table-scroll mb-6">
                <table>
                    <caption class="sr-only">Parametry z tabeli katalogu dla wybranych gatunków</caption>
                    <thead>
                        <tr>
                            <th scope="col">Gatunek</th>
                            <th scope="col">Temperatura</th>
                            <th scope="col">pH</th>
                            <th scope="col">Wielkość akwarium</th>
                            <th scope="col">Temperament</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($result->species as $entry)
                            <tr>
                                <th scope="row">
                                    <a href="{{ route('catalog.show', ['fauna', $entry->id]) }}">{{ $entry->name }}</a>
                                </th>
                                <td>{{ $entry->fields['Temperatura'] ?? 'brak danych' }}</td>
                                <td>{{ $entry->fields['pH'] ?? 'brak danych' }}</td>
                                <td>{{ $entry->fields['WielkośćAkwarium'] ?? 'brak danych' }}</td>
                                <td>{{ $entry->fields['Temperament'] ?? 'brak danych' }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            <h3>Porównanie par (temperatura i pH)</h3>
            @if ($pairFindings !== [])
                @include('aquarium._findings', ['findings' => $pairFindings])
            @else
                <p>Dla sprawdzonych par pasma temperatury i pH w katalogu są zgodne.</p>
            @endif

            <h3 class="mt-6">Temperament</h3>
            @if ($temperamentFindings !== [])
                @include('aquarium._findings', ['findings' => $temperamentFindings])
            @endif
            <p class="mt-3 font-medium">Relacja pary: brak danych.</p>

            <h3 class="mt-6">Podsumowanie kontroli</h3>
            <div class="table-scroll">
                <table>
                    <caption class="sr-only">Kontrole porównania gatunków</caption>
                    <thead>
                        <tr>
                            <th scope="col">Kontrola</th>
                            <th scope="col">Z danymi</th>
                            <th scope="col">Brak danych</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($publicRows as $row)
                            <tr>
                                <th scope="row">{{ $row->check->label() }}</th>
                                @if ($row->checked === 0 && $row->noData === 0)
                                    <td colspan="2">Brak danych</td>
                                @else
                                    <td>{{ $row->checked }} {{ $row->check->unit() }}</td>
                                    <td>{{ $row->noData }} {{ $row->check->unit() }}</td>
                                @endif
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            @if ($result->report->unknown > 0)
                <p class="mt-4 text-sm text-muted">Brak danych katalogowych dla {{ $result->report->unknown }} pozycji.</p>
            @endif
        </section>

        @if ($canOffers)
            <section aria-labelledby="together-offers-heading">
                <h2 id="together-offers-heading">Oferty w okolicy</h2>
                @if ($nearbyOnly && $userCity)
                    <p class="text-text-2">Wyświetlane dla miasta z Twojego konta: <strong>{{ $userCity }}</strong>.</p>
                @else
                    <p class="text-text-2">Widoczne oferty lokalnych sklepów akwarystycznych dla wybranych gatunków.</p>
                @endif
                <div class="mt-4 space-y-6">
                    @foreach ($result->species as $entry)
                        <div>
                            <h3 class="mb-2">
                                <a href="{{ route('catalog.show', ['fauna', $entry->id]) }}">{{ $entry->name }}</a>
                            </h3>
                            <x-advisor-card-actions
                                :area="Area::Fauna"
                                :id="$entry->id"
                                :name="$entry->name"
                                variant="offers"
                                :offers="$offersById[$entry->id] ?? []"
                                :nearby-only="$nearbyOnly"
                            />
                        </div>
                    @endforeach
                </div>
            </section>
        @endif
    @endif
@endsection

@php($draft = $plan->state === \App\Domain\Aquarium\Models\TankPlan::DRAFT)
<div>
    <p><a href="{{ route('plans.index') }}">&larr; Plany</a></p>
    <h1>{{ $plan->name }}</h1>
    <p>{{ $plan->type->value }}, {{ $plan->size_band->label() }}@if ($plan->liters) ({{ $plan->liters }} l)@endif</p>
    @error('form')
        <x-alert type="error" role="alert">{{ $message }}</x-alert>
    @enderror
    @error('plan')
        <x-alert type="error" role="alert">{{ $message }}</x-alert>
    @enderror

    <section aria-labelledby="items-heading">
        <h2 id="items-heading">Wybrane przez Ciebie pozycje</h2>
        @forelse ($report->lines as $line)
            <p wire:key="line-{{ $line['area']->value }}-{{ $line['id'] }}">
                <a href="{{ route('catalog.show', [$line['area']->value, $line['id']]) }}">{{ $line['entry']?->name ?? $line['id'] }}</a>
                @if ($line['entry']?->nameLatin)<i>({{ $line['entry']->nameLatin }})</i>@endif
                — {{ $line['quantity'] }} szt.
                @if ($draft)
                    <button type="button" wire:click="remove('{{ $line['area']->value }}', '{{ $line['id'] }}')" aria-label="Usuń z planu: {{ $line['entry']?->name ?? $line['id'] }}">Usuń</button>
                @endif
            </p>
        @empty
            <p>Plan nie ma jeszcze gatunków.</p>
        @endforelse

        @if ($draft)
            <h3>Dodaj gatunek</h3>
            @error('species')
                <p role="alert">{{ $message }}</p>
            @enderror
            <div>
                <label for="plan-phrase">Szukaj ryby, bezkręgowca lub rośliny</label>
                <input id="plan-phrase" type="search" wire:model.live.debounce.300ms="phrase" autocomplete="off">
            </div>
            <div>
                <label for="plan-quantity">Ilość</label>
                <input id="plan-quantity" type="number" min="1" max="9999" wire:model="quantity">
                @error('quantity')
                    <p role="alert">{{ $message }}</p>
                @enderror
            </div>
            @if ($phrase !== '')
                <ul aria-label="Wyniki wyszukiwania" aria-live="polite">
                    @forelse ($results as $entry)
                        <li wire:key="result-{{ $entry->area->value }}-{{ $entry->id }}">
                            {{ $entry->name }} @if ($entry->nameLatin)<i>({{ $entry->nameLatin }})</i>@endif
                            <button type="button" wire:click="add('{{ $entry->area->value }}', '{{ $entry->id }}')" aria-label="Dodaj do planu: {{ $entry->name }}">Dodaj</button>
                        </li>
                    @empty
                        <li>Brak wyników (wpisz co najmniej kilka liter nazwy).</li>
                    @endforelse
                </ul>
            @endif
        @endif
    </section>

    @if ($report->lines !== [])
        <section aria-labelledby="compat-section">
            <h2 id="compat-section">Porównanie z katalogiem</h2>
            @include('aquarium._compatibility-card', ['report' => $report->compatibility, 'hasStock' => true])
        </section>
    @endif

    <section aria-labelledby="equipment-heading">
        <h2 id="equipment-heading">Sprzęt w katalogu do przejrzenia</h2>
        <p>Filtr katalogu: WielkośćAkwarium zawiera „{{ $plan->size_band->label() }}” lub „Wszystkie”, TypAkwarium zawiera „{{ $plan->type->value }}”. To lista rekordów katalogu do przeglądania, a nie ocena, czego potrzebuje zbiornik.</p>
        @forelse ($report->categories as $name => $count)
            <a class="button button-secondary" href="{{ route('plans.show', ['plan' => $plan->id, 'kategoria' => $name]) }}#equipment-heading" @if ($name === $report->category) aria-current="true" @endif>{{ $name }} ({{ $count }})</a>
        @empty
            <p>Brak danych: katalog nie ma sprzętu dla tego pasma i typu.</p>
        @endforelse
        @if ($report->category !== null)
            <ul aria-label="Sprzęt: {{ $report->category }}">
                @foreach ($report->equipment as $item)
                    <li><a href="{{ route('catalog.show', [$item->area->value, $item->id]) }}">{{ $item->name }}</a>@if (($item->fields['rodzaj'] ?? '') !== '') — {{ $item->fields['rodzaj'] }}@endif</li>
                @endforeach
            </ul>
        @endif
    </section>

    <section aria-labelledby="shopping-heading">
        <h2 id="shopping-heading">Lista zakupów</h2>
        @if ($shopping === null)
            <p>Oferty sklepów są niedostępne dla tego konta.</p>
        @elseif ($report->lines === [])
            <p>Dodaj gatunki do planu, aby zobaczyć oferty.</p>
        @else
            @if ($shopping->city !== null)
                <p>Oferty z okolicy: {{ $shopping->city }}.</p>
            @else
                <p>Pokazujemy oferty ze wszystkich miast. <a href="{{ route('account') }}">Podaj miasto w koncie</a>, aby zawęzić je do okolicy.</p>
            @endif
            @if ($errors->has('qty'))
                <x-alert type="error" role="alert">{{ $errors->first('qty') }}</x-alert>
            @endif
            @foreach ($shopping->groups as $group)
                <x-card class="mb-4">
                    <h3>{{ $group['shop'] }} — {{ $group['location'] }}</h3>
                    <p>{{ $group['address'] }}, {{ $group['city'] }}</p>
                    <div class="table-scroll">
                        <table>
                            <caption class="sr-only">Oferty: {{ $group['shop'] }}, {{ $group['location'] }}</caption>
                            <thead>
                                <tr><th scope="col">Pozycja</th><th scope="col">Wariant</th><th scope="col">W planie</th><th scope="col">Dostępne</th><th scope="col">Cena</th><th scope="col"><span class="sr-only">Koszyk</span></th></tr>
                            </thead>
                            <tbody>
                                @foreach ($group['lines'] as $line)
                                    @php($take = min($line['wanted'], $line['freeQty']))
                                    <tr>
                                        <th scope="row">{{ $line['name'] }}</th>
                                        <td>{{ $line['variant'] !== '' ? $line['variant'] : 'bez wariantu' }}</td>
                                        <td>{{ $line['wanted'] }}</td>
                                        <td>{{ $line['freeQty'] }}@if ($line['freeQty'] < $line['wanted']) (dostępne {{ $line['freeQty'] }} z {{ $line['wanted'] }})@endif</td>
                                        <td>{{ number_format($line['priceGrosze'] / 100, 2, ',', ' ') }} zł</td>
                                        <td>
                                            @if ($canCart)
                                                <form method="POST" action="{{ route('cart.add') }}">
                                                    @csrf
                                                    <input type="hidden" name="offer_id" value="{{ $line['offerId'] }}">
                                                    <input type="hidden" name="qty" value="{{ $take }}">
                                                    <x-button type="submit" variant="secondary" :aria-label="'Do koszyka: '.$line['name'].', '.$take.' szt.'">Do koszyka ({{ $take }})</x-button>
                                                </form>
                                            @endif
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </x-card>
            @endforeach
            @if ($shopping->missing !== [])
                <h3>Pozycje bez ofert</h3>
                <ul>
                    @foreach ($shopping->missing as $item)
                        <li>{{ $item['name'] }} ({{ $item['wanted'] }} szt.) — brak ofert w okolicy</li>
                    @endforeach
                </ul>
            @endif
        @endif
    </section>

    <section aria-labelledby="convert-heading">
        <h2 id="convert-heading">Zbiornik z planu</h2>
        @if ($draft)
            <p>Po zakupie zamień plan w zbiornik: przeniesiemy nazwę, typ, wielkość, litraż i obsadę. Plan można zamienić tylko raz.</p>
            <x-button type="button" wire:click="convert" wire:loading.attr="disabled" wire:confirm="Utworzyć zbiornik z tego planu?">Utwórz akwarium z planu</x-button>
        @elseif ($tank)
            <p>Plan został zamieniony w zbiornik. <a href="{{ route('aquarium.tank', $tank) }}">Przejdź do zbiornika</a></p>
        @else
            <p>Plan został zamieniony w zbiornik, który został usunięty.</p>
        @endif
    </section>
</div>

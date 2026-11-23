@php
    $tz = config('app.display_timezone');
    $formatNumber = static function (float $value, int $decimals = 2): string {
        $formatted = number_format($value, $decimals, ',', ' ');
        $trimmed = rtrim(rtrim($formatted, '0'), ',');

        return $trimmed === '-0' ? '0' : $trimmed;
    };
    $formatSigned = static function (float $value, int $decimals = 2) use ($formatNumber): string {
        $text = $formatNumber($value, $decimals);

        return $value > 0 ? '+'.$text : $text;
    };
@endphp

<div class="min-w-0 space-y-8">
    @if ($trendsReport !== null)
        <section aria-labelledby="trends-heading" class="!mt-0 min-w-0 space-y-6 !border-0 !p-0 !shadow-none">
            <div class="flex flex-wrap items-start justify-between gap-4">
                <div class="min-w-0">
                    <h3 id="trends-heading" class="!mb-1">Trendy pomiarów</h3>
                    <p class="!mb-0 text-sm text-muted">Historia zmian, nie prognoza.</p>
                </div>

                <nav aria-label="Okno czasowe trendów" class="flex flex-wrap gap-2">
                    @foreach ($trendsReport->availableWindows as $window)
                        @php $activeWindow = $window === $trendsReport->windowDays; @endphp
                        <a href="{{ route('aquarium.tank', ['tank' => $tankId, 'karta' => 'historia', 'okno' => $window]) }}"
                            wire:click.prevent="$set('windowDays', {{ $window }})"
                            @class(['button', 'button-secondary' => ! $activeWindow])
                            @if ($activeWindow) aria-current="true" @endif>
                            {{ $window }} dni
                        </a>
                    @endforeach
                </nav>
            </div>

            @if ($trendsReport->trends === [])
                <x-alert type="info">
                    Brak pomiarów w oknie ostatnich {{ $trendsReport->windowDays }} dni. Zmień zakres czasu powyżej lub dodaj pomiar.
                </x-alert>
            @else
                <div class="space-y-6">
                    @foreach ($trendsReport->trends as $item)
                        @php
                            $trend = $item->trend;
                            $chart = $charts[$item->parameter] ?? null;
                            $decimals = $item->parameter === 'po4' || $item->parameter === 'no2' || $item->parameter === 'nh4' ? 3 : 2;
                        @endphp
                        <article class="!m-0 min-w-0 space-y-4">
                            <div class="flex flex-wrap items-baseline justify-between gap-2">
                                <h4 class="!m-0">{{ $item->label }}</h4>
                                <span class="text-sm text-muted">Pomiarów w oknie {{ $trendsReport->windowDays }} dni: {{ $trend->pointCount }}</span>
                            </div>

                            @if (! $trend->enoughData)
                                <x-alert type="info" role="status">
                                    {{ $trend->statusMessage }} (zapisano {{ $trend->pointCount }}, wymagane co najmniej {{ $trend->minPoints }} w oknie {{ $trendsReport->windowDays }} dni).
                                </x-alert>
                            @else
                                <dl class="!my-0">
                                    <div>
                                        <dt>Zmiana (pierwszy &rarr; ostatni)</dt>
                                        <dd>{{ $formatSigned($trend->delta ?? 0.0, $decimals) }}</dd>
                                    </div>
                                    <div>
                                        <dt>Średnie tempo na dobę</dt>
                                        <dd>
                                            @if ($trend->ratePerDay !== null)
                                                {{ $formatSigned($trend->ratePerDay, 3) }} / dobę
                                            @else
                                                brak odstępu czasu
                                            @endif
                                        </dd>
                                    </div>
                                    <div>
                                        <dt>Zakres odczytów</dt>
                                        <dd>{{ $formatNumber($trend->firstValue ?? 0.0, $decimals) }} &rarr; {{ $formatNumber($trend->lastValue ?? 0.0, $decimals) }}</dd>
                                    </div>
                                </dl>

                                @if ($trend->mixedMethods)
                                    <x-alert type="info" role="status">
                                        Uwaga: w tym oknie użyto różnych metod pomiaru ({{ implode(', ', array_map(fn ($m) => $m->label(), $trend->methods)) }}). Porównuj wyniki ostrożnie.
                                    </x-alert>
                                @elseif ($trend->hasUnspecifiedMethod && $trend->methods !== [])
                                    <p class="!mb-0 text-sm text-muted">
                                        Część pomiarów w tym oknie nie ma zapisanej metody ({{ implode(', ', array_map(fn ($m) => $m->label(), $trend->methods)) }}).
                                    </p>
                                @endif

                                @if ($chart !== null)
                                    @php
                                        $displayTz = new \DateTimeZone($tz);
                                        $firstDate = \DateTimeImmutable::createFromInterface($trend->points[0]->measuredAt)->setTimezone($displayTz)->format('d.m.Y');
                                        $lastDate = \DateTimeImmutable::createFromInterface($trend->points[count($trend->points) - 1]->measuredAt)->setTimezone($displayTz)->format('d.m.Y');
                                        $ariaLabel = "Wykres historii zmian {$item->label} w oknie {$trendsReport->windowDays} dni: {$trend->pointCount} pomiary, od {$formatNumber($trend->firstValue ?? 0.0, $decimals)} do {$formatNumber($trend->lastValue ?? 0.0, $decimals)}.";
                                    @endphp
                                    <figure class="m-0 min-w-0">
                                        <svg viewBox="0 0 {{ $chart['width'] }} {{ $chart['height'] }}"
                                            class="block h-auto w-full max-w-full rounded-xl border border-line bg-white"
                                            role="img"
                                            aria-label="{{ $ariaLabel }}">
                                            <title>{{ $item->label }} — historia zmian ({{ $trendsReport->windowDays }} dni)</title>

                                            @foreach ($chart['bands'] as $band)
                                                <rect x="{{ $chart['plotLeft'] }}"
                                                    y="{{ $band['y'] }}"
                                                    width="{{ $chart['plotRight'] - $chart['plotLeft'] }}"
                                                    height="{{ $band['height'] }}"
                                                    fill="#d6f0ea"
                                                    fill-opacity="0.65" />
                                                @if ($band['hasMax'])
                                                    <line x1="{{ $chart['plotLeft'] }}" y1="{{ $band['y'] }}" x2="{{ $chart['plotRight'] }}" y2="{{ $band['y'] }}"
                                                        stroke="#176b68" stroke-width="1" stroke-dasharray="4 4" />
                                                @endif
                                                @if ($band['hasMin'])
                                                    <line x1="{{ $chart['plotLeft'] }}" y1="{{ $band['y'] + $band['height'] }}" x2="{{ $chart['plotRight'] }}" y2="{{ $band['y'] + $band['height'] }}"
                                                        stroke="#176b68" stroke-width="1" stroke-dasharray="4 4" />
                                                @endif
                                            @endforeach

                                            @foreach ($chart['yTicks'] as $tick)
                                                <line x1="{{ $chart['plotLeft'] }}" y1="{{ $tick['y'] }}" x2="{{ $chart['plotRight'] }}" y2="{{ $tick['y'] }}"
                                                    stroke="#e3e9e7" stroke-width="1" />
                                                <text x="{{ $chart['plotLeft'] - 8 }}" y="{{ $tick['y'] + 4 }}"
                                                    fill="#3f4d4a" font-size="11" text-anchor="end">{{ $formatNumber($tick['value'], $decimals) }}</text>
                                            @endforeach

                                            <line x1="{{ $chart['plotLeft'] }}" y1="{{ $chart['plotBottom'] }}" x2="{{ $chart['plotRight'] }}" y2="{{ $chart['plotBottom'] }}"
                                                stroke="#bfcbc7" stroke-width="1" />

                                            <text x="{{ $chart['plotLeft'] }}" y="{{ $chart['height'] - 10 }}" fill="#3f4d4a" font-size="11" text-anchor="start">{{ $firstDate }}</text>
                                            <text x="{{ $chart['plotRight'] }}" y="{{ $chart['height'] - 10 }}" fill="#3f4d4a" font-size="11" text-anchor="end">{{ $lastDate }}</text>

                                            <polyline fill="none" stroke="#176b68" stroke-width="2.5" stroke-linejoin="round" stroke-linecap="round"
                                                points="{{ $chart['polyline'] }}" />

                                            @foreach ($chart['points'] as $pt)
                                                <circle cx="{{ $pt['x'] }}" cy="{{ $pt['y'] }}" r="4.5" fill="#176b68" stroke="#ffffff" stroke-width="1.5" />
                                            @endforeach
                                        </svg>

                                        <figcaption class="mt-2 text-xs text-muted">
                                            Linia z punktami: zapisane odczyty.
                                            @if ($item->stockRanges !== [])
                                                Tło z linią przerywaną: wspólny zakres katalogowy obsady ({{ implode(' lub ', array_column($item->stockRanges, 'label')) }}).
                                            @elseif ($item->hasStockRangeSupport)
                                                Brak tła zakresu obsady (niepełne, rozbieżne lub sprzeczne dane katalogowe obsady).
                                            @endif
                                        </figcaption>
                                    </figure>
                                @endif
                            @endif

                            <div class="table-scroll">
                                <table>
                                    <caption>Odczyty: {{ $item->label }} (ostatnie {{ $trendsReport->windowDays }} dni)</caption>
                                    <thead>
                                        <tr>
                                            <th scope="col">Data</th>
                                            <th scope="col">Wartość</th>
                                            <th scope="col">Metoda</th>
                                            <th scope="col">Uwagi</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        @foreach ($item->rows as $entry)
                                            <tr>
                                                <th scope="row">{{ $entry['measured_at']->timezone($tz)->format('d.m.Y H:i') }}</th>
                                                <td>{{ str_replace('.', ',', $entry['value']) }}</td>
                                                <td>{{ $entry['method_label'] ?? '—' }}</td>
                                                <td>{{ $entry['warnings'] !== [] ? implode(' ', $entry['warnings']) : '—' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </section>
    @endif

    <div class="min-w-0">
        <h3>Wszystkie pomiary</h3>
        @if ($rows->isEmpty())
            <p>Brak zapisanych pomiarów.</p>
        @else
            <div class="table-scroll"><table>
                <caption>Pomiary od najnowszego</caption>
                <thead>
                    <tr>
                        <th scope="col">Data</th>
                        <th scope="col">Metoda</th>
                        @foreach ($labels as $label)
                            <th scope="col">{{ $label }}</th>
                        @endforeach
                        <th scope="col">Uwagi</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($rows as $row)
                        <tr wire:key="m-{{ $row['m']->id }}">
                            <th scope="row">{{ $row['m']->measured_at->timezone($tz)->format('d.m.Y H:i') }}</th>
                            <td>{{ $row['m']->method?->label() ?? '—' }}</td>
                            @foreach ($labels as $param => $label)
                                <td>{{ $row['m']->$param ?? '—' }}</td>
                            @endforeach
                            <td>{{ implode(' ', $row['warnings']) }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table></div>
        @endif
    </div>
</div>

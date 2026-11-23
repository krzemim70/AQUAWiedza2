@push('styles')
    <style>
        .offer-import {
            color: var(--color-ink, #14201f);
            display: grid;
            gap: 1.5rem;
            min-width: 0;
        }
        .offer-import .card {
            background: #fff;
            border: 1px solid var(--color-line, #e3e9e7);
            border-radius: var(--radius-card, 1.25rem);
            display: grid;
            gap: 1rem;
            min-width: 0;
            padding: clamp(1rem, 3vw, 2rem);
        }
        .offer-import h2 { font-size: 1.5rem; font-weight: 650; line-height: 1.2; }
        .offer-import form, .offer-import .import-field { display: grid; gap: .75rem; justify-items: start; }
        .offer-import .import-actions { display: flex; flex-wrap: wrap; gap: .75rem; align-items: center; }
        .offer-import .button, .offer-import input[type="file"]::file-selector-button {
            background: var(--color-ink, #14201f);
            border: 1px solid transparent;
            border-radius: 999px;
            color: #fff;
            cursor: pointer;
            font-weight: 600;
            min-height: 2.75rem;
            padding: .5rem 1rem;
        }
        .offer-import .button { display: inline-flex; align-items: center; gap: .5rem; }
        .offer-import .button:hover { text-decoration: underline; }
        .offer-import .button-secondary, .offer-import input[type="file"]::file-selector-button {
            background: #fff;
            border-color: var(--color-muted, #566360);
            color: var(--color-ink, #14201f);
        }
        .offer-import input[type="file"] { max-width: 100%; min-height: 2.75rem; }
        .offer-import input[type="file"]::file-selector-button { margin-inline-end: .75rem; }
        .offer-import :focus-visible { outline: 3px solid var(--color-focus, #2f8f88); outline-offset: 3px; }
        .offer-import .import-icon { width: 1.15rem; height: 1.15rem; }
        .offer-import .import-confirm { display: flex; gap: .75rem; align-items: center; min-height: 2.75rem; cursor: pointer; }
        .offer-import .import-confirm input { flex: none; width: 1.25rem; height: 1.25rem; }
        .offer-import .import-meta { color: var(--color-muted, #566360); }
        .offer-import .import-error { color: var(--color-danger, #943432); }
        .offer-import .import-counts { display: flex; flex-wrap: wrap; gap: .75rem 1.5rem; }
        .offer-import .import-counts dd { font-size: 1.25rem; font-weight: 650; }
        .offer-import .import-table { overflow-x: auto; }
        .offer-import table { width: 100%; text-align: left; }
        .offer-import caption { padding-bottom: .5rem; text-align: left; }
        .offer-import :is(th, td) {
            border-bottom: 1px solid var(--color-line, #e3e9e7);
            padding: .6rem .75rem;
            vertical-align: top;
            min-width: 7rem;
            overflow-wrap: anywhere;
        }
        .offer-import :is(th, td):first-child { min-width: 0; }
        .offer-import td:last-child { min-width: 11rem; }
    </style>
@endpush

<x-filament-panels::page>
    <div class="offer-import">
        <x-card>
            <h2>Sprawdź plik CSV</h2>
            <p id="import-format" class="import-meta">
                Wpisz ID rekordu lub jego dokładną nazwę w kolumnie <code>id_rekordu</code>.
                Użyj średnika lub przecinka jako separatora. Daty podaj w formacie RRRR-MM-DD.
                Maksymalnie {{ number_format($limits['max_rows'], 0, ',', ' ') }} wierszy i
                {{ number_format($limits['max_bytes'], 0, ',', ' ') }} bajtów.
            </p>
            <form method="POST" enctype="multipart/form-data" action="{{ route('shop.offer-import.preview', $shop) }}">
                @csrf
                <div class="import-field">
                    <label for="offer-csv">Plik CSV</label>
                    <input id="offer-csv" type="file" name="file" accept=".csv,text/csv" required
                        aria-describedby="import-format @error('file') import-file-error @enderror"
                        @error('file') aria-invalid="true" @enderror>
                    @error('file')
                        <p id="import-file-error" class="import-error" role="alert">{{ $message }}</p>
                    @enderror
                </div>
                <div class="import-actions">
                    <x-button type="submit">Sprawdź plik</x-button>
                    <x-button variant="secondary" :href="route('shop.offer-import.template', $shop)">
                        <x-heroicon-o-arrow-down-tray class="import-icon" aria-hidden="true" /> Pobierz wzór CSV
                    </x-button>
                </div>
            </form>
        </x-card>

        @if ($batch)
            @php
                $preview = $batch->state === 'preview';
                $queued = $batch->state === 'queued';
                $report = $batch->report;
                $counts = $batch->counts;
                $headings = $preview
                    ? ['added' => 'Nowe', 'updated' => 'Zmienione', 'unchanged' => 'Bez zmian', 'errors' => 'Błędy']
                    : ['added' => 'Dodane', 'updated' => 'Zaktualizowane', 'unchanged' => 'Pominięte — bez zmian', 'errors' => 'Pominięte — błędy'];
                $rowLabels = $preview
                    ? ['added' => 'Nowa', 'updated' => 'Zmieniona', 'unchanged' => 'Bez zmian', 'errors' => 'Błąd']
                    : ['added' => 'Dodana', 'updated' => 'Zaktualizowana', 'unchanged' => 'Bez zmian', 'errors' => 'Pominięta'];
            @endphp
            <x-card>
                <div @if ($queued) wire:poll.3s="refreshImport" @endif aria-live="polite" aria-atomic="true">
                    <h2>{{ $preview ? 'Podgląd importu' : 'Wynik importu' }}</h2>
                    @if ($queued)
                        <p>Import w kolejce lub w trakcie zapisu. Przetworzono {{ array_sum($counts) }} z {{ $report['total'] }} wierszy.</p>
                    @elseif ($batch->state === 'applied')
                        <p>Import zakończony.</p>
                    @elseif ($batch->state === 'failed')
                        <p class="import-error">{{ $report['failure'] }} Pozostało {{ $report['total'] - array_sum($counts) }} wierszy.</p>
                    @endif
                    <dl class="import-counts">
                        @foreach ($headings as $key => $label)
                            <div><dt>{{ $label }}</dt><dd>{{ $counts[$key] }}</dd></div>
                        @endforeach
                    </dl>
                </div>
                <p class="import-meta">Kodowanie {{ $report['encoding'] }} · separator {{ $report['separator'] === ';' ? 'średnik' : 'przecinek' }}</p>

                <div class="import-actions">
                    @if ($queued)
                        <x-button variant="secondary" :href="\App\Filament\Shop\Pages\OfferImport::getUrl(['import' => $batch->id], tenant: $shop)">
                            Odśwież wynik
                        </x-button>
                    @endif
                    @if ($report['errors'] !== [])
                        <x-button variant="secondary" :href="route('shop.offer-import.errors', [$shop, $batch->id])">
                            <x-heroicon-o-arrow-down-tray class="import-icon" aria-hidden="true" /> Pobierz błędy CSV
                        </x-button>
                    @endif
                </div>

                <div class="import-table" tabindex="0" role="region" aria-labelledby="import-rows-caption">
                    <table>
                        <caption id="import-rows-caption">
                            Wiersze pliku{{ $report['total'] > count($report['rows']) ? ' (pierwsze '.count($report['rows']).' z '.$report['total'].')' : '' }}
                        </caption>
                        <thead><tr>
                            <th scope="col">Wiersz</th>
                            <th scope="col">Rekord</th>
                            <th scope="col">Placówka</th>
                            <th scope="col">Wariant</th>
                            <th scope="col">Cena</th>
                            <th scope="col">Dostępna ilość</th>
                            <th scope="col">Ważność</th>
                            <th scope="col">Publikacja</th>
                            <th scope="col">Status</th>
                        </tr></thead>
                        <tbody>
                            @foreach ($report['rows'] as $line => $row)
                                @php
                                    $reasons = $report['errors'][$line] ?? [];
                                    $status = $reasons !== [] ? 'errors' : $row['status'];
                                @endphp
                                <tr>
                                    <th scope="row">{{ $line }}</th>
                                    <td>{{ ($row['record'] ?? '') ?: '—' }}</td>
                                    <td>{{ ($row['location'] ?? '') ?: '—' }}</td>
                                    <td>{{ ($row['variant'] ?? '') !== '' ? $row['variant'] : '—' }}</td>
                                    <td>
                                        @if (isset($row['price']))
                                            @if (isset($row['previous_price']) && $row['previous_price'] !== $row['price'])
                                                {{ \App\Filament\Shop\Support::money($row['previous_price']) }} →
                                            @endif
                                            {{ \App\Filament\Shop\Support::money($row['price']) }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>
                                        @if (isset($row['qty'], $row['previous_qty']) && $row['previous_qty'] !== $row['qty'])
                                            {{ $row['previous_qty'] }} →
                                        @endif
                                        {{ $row['qty'] ?? '—' }}
                                    </td>
                                    <td>
                                        @if (isset($row['from'], $row['to']))
                                            {{ $row['from'] }}<br>do {{ $row['to'] }}
                                        @else
                                            —
                                        @endif
                                    </td>
                                    <td>{{ isset($row['publish']) ? ($row['publish'] ? 'Publikuj' : 'Zachowaj status') : '—' }}</td>
                                    <td>
                                        @if (! $preview && $line > $batch->last_line && $status !== 'errors')
                                            {{ $queued ? 'Oczekuje na zapis' : 'Pominięta — '.$report['failure'] }}
                                        @else
                                            {{ $rowLabels[$status] }}
                                        @endif
                                        @foreach ($reasons as $reason)
                                            <p class="import-error">{{ $reason }}</p>
                                        @endforeach
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                @if ($preview && $counts['errors'] < $report['total'])
                    <form method="POST" action="{{ route('shop.offer-import.apply', [$shop, $batch->id]) }}">
                        @csrf
                        <label class="import-confirm">
                            <input type="checkbox" name="confirmed" value="1" required
                                @error('confirmed') aria-invalid="true" aria-describedby="import-confirm-error" @enderror>
                            Potwierdzam, że dostępne ilości zgadzają się ze stanem fizycznym.
                        </label>
                        @error('confirmed')
                            <p id="import-confirm-error" class="import-error" role="alert">{{ $message }}</p>
                        @enderror
                        <x-button type="submit">Zapisz oferty</x-button>
                    </form>
                @endif
            </x-card>
        @endif
    </div>
</x-filament-panels::page>

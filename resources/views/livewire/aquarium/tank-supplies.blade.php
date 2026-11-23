@php
    $addErrors = $errors->getBag('supply');
    $formatAmount = static fn (float $value): string => rtrim(rtrim(number_format($value, 2, ',', ' '), '0'), ',');
@endphp
<div class="[&_a]:scroll-mt-24 [&_button]:scroll-mt-24 [&_input]:scroll-mt-24 [&_select]:scroll-mt-24">
    <div aria-live="polite" aria-atomic="true">
        @if (session('supply_status'))
            <x-alert type="success">{{ session('supply_status') }}</x-alert>
        @endif
    </div>

    <p class="text-sm text-muted">
        Stan i liczba dni to szacunek na podstawie wpisanych przez Ciebie dawek, harmonogramu i zdarzeń.
        Limit aktywnych pozycji na tym koncie: {{ count($items) }} / {{ $limit }}.
    </p>

    @if ($items === [])
        <p>Brak zapisanych zapasów w tym zbiorniku.</p>
    @else
        <ul class="m-0 list-none space-y-4 p-0">
            @foreach ($items as $item)
                @php
                    $row = $item['row'];
                    $name = $item['name'];
                    $projection = $item['projection'];
                    $reorderOffer = $item['reorderOffer'];
                    $preferredMissing = $item['preferredMissing'];
                    $unitLabel = $row->unit->label();
                    $rowErrors = $errors->getBag('supply-'.$row->id);
                    $displayName = $name ?? $row->catalog_id;
                    $useKey = (string) \Illuminate\Support\Str::uuid();
                    $restockKey = (string) \Illuminate\Support\Str::uuid();
                @endphp
                <li id="supply-{{ $row->id }}" class="scroll-mt-24">
                    <x-card class="space-y-4 !p-5">
                        <div class="flex flex-wrap items-start justify-between gap-4">
                            <div class="min-w-0 space-y-1">
                                <h3 class="!mb-0 wrap-break-word">
                                    @if ($name !== null)
                                        <a class="inline-flex min-h-11 items-center" href="{{ route('catalog.show', ['care', $row->catalog_id]) }}">{{ $name }}</a>
                                    @else
                                        {{ $row->catalog_id }}
                                    @endif
                                </h3>
                                <p class="!mb-0 text-sm text-muted">
                                    {{ $name === null ? 'Brak rekordu w katalogu' : $row->catalog_id }}
                                </p>
                                <p class="!mb-0">
                                    Pozostało: <strong>{{ $formatAmount($projection->remainingAmount) }} {{ $unitLabel }}</strong>
                                    <span class="text-muted">(opakowanie {{ $formatAmount((float) $row->package_amount) }} {{ $unitLabel }})</span>
                                </p>
                                <p class="!mb-0 text-sm text-muted">
                                    @if ($row->dose_per_liters !== null)
                                        Dawka z etykiety: {{ $formatAmount((float) $row->dose_amount) }} {{ $unitLabel }} na {{ $formatAmount((float) $row->dose_per_liters) }} l
                                        @if ($projection->dosePerUse !== null)
                                            (dla {{ $tank->volume_liters }} l: {{ $formatAmount($projection->dosePerUse) }} {{ $unitLabel }})
                                        @endif
                                    @else
                                        Stała porcja: {{ $formatAmount((float) $row->dose_amount) }} {{ $unitLabel }}
                                    @endif
                                    ·
                                    @if ($row->interval_days !== null)
                                        Harmonogram: co {{ $row->interval_days }} {{ $row->interval_days === 1 ? 'dzień' : 'dni' }}
                                    @else
                                        Odejmowanie tylko po „Zastosowałem”
                                    @endif
                                </p>
                                @if ($row->preferred_shop_id !== null)
                                    <p class="!mb-0 text-sm text-muted">
                                        Preferowany sklep: {{ $shopNames[$row->preferred_shop_id] ?? 'sklep nr '.$row->preferred_shop_id }}
                                    </p>
                                @endif
                            </div>

                            <div class="text-sm">
                                @if ($projection->missingDataReason !== null)
                                    <p class="!mb-1 font-semibold text-danger" role="status">
                                        {{ $projection->missingDataReason }}
                                    </p>
                                    <a href="{{ route('calculators.volume') }}" class="inline-flex min-h-11 items-center">Uzupełnij litraż zbiornika</a>
                                @elseif ($projection->daysRemaining !== null)
                                    <p class="!mb-0 font-semibold">
                                        zostało około {{ $projection->daysRemaining }} {{ $projection->daysRemaining === 1 ? 'dnia' : 'dni' }}
                                    </p>
                                    <p class="!mb-0 text-xs text-muted">Szacunek z harmonogramu użytkownika</p>
                                @else
                                    <p class="!mb-0 text-muted">Stan bez harmonogramu (bez prognozy dni).</p>
                                @endif
                            </div>
                        </div>

                        @if ($rowErrors->any())
                            <x-alert type="error" role="alert">{{ $rowErrors->first() }}</x-alert>
                        @endif

                        <div class="flex flex-wrap items-center gap-3">
                            <form method="POST" action="{{ route('aquarium.supplies.event', [$tankId, $row->id]) }}" class="!m-0">
                                @csrf
                                <input type="hidden" name="kind" value="use">
                                <input type="hidden" name="idempotency_key" value="{{ $useKey }}">
                                <x-button type="submit" :aria-label="'Zastosowałem: '.$displayName">Zastosowałem</x-button>
                            </form>

                            <form method="POST" action="{{ route('aquarium.supplies.event', [$tankId, $row->id]) }}" class="!m-0">
                                @csrf
                                <input type="hidden" name="kind" value="restock">
                                <input type="hidden" name="idempotency_key" value="{{ $restockKey }}">
                                <x-button type="submit" variant="secondary" :aria-label="'Otwórz nowe opakowanie: '.$displayName">Otwórz nowe opakowanie</x-button>
                            </form>

                            @if ($reorderOffer !== null && $canCart)
                                <form method="POST" action="{{ route('cart.add') }}" class="!m-0 flex flex-wrap items-center gap-2">
                                    @csrf
                                    <input type="hidden" name="offer_id" value="{{ $reorderOffer->id }}">
                                    <input type="hidden" name="qty" value="1">
                                    <x-button type="submit" variant="secondary" :aria-label="'Zamów ponownie: '.$displayName.' — '.$reorderOffer->shopName">
                                        Zamów ponownie ({{ $reorderOffer->shopName }})
                                    </x-button>
                                    <span class="text-sm text-muted">
                                        <x-offer-price :offer="$reorderOffer" :strong="false" />
                                        @if ($preferredMissing)
                                            · preferowany sklep nie ma teraz oferty, propozycja z okolicy
                                        @endif
                                    </span>
                                </form>
                            @else
                                <span class="text-sm text-muted">
                                    @if ($preferredMissing)
                                        Preferowany sklep nie ma teraz oferty.
                                    @endif
                                    Brak dostępnych ofert{{ $hasCity ? ' w okolicy' : '' }}.
                                    <a href="{{ route('catalog.show', ['care', $row->catalog_id]) }}" class="inline-flex min-h-11 items-center">Zobacz w katalogu</a>
                                </span>
                            @endif

                            <form method="POST" action="{{ route('aquarium.supplies.archive', [$tankId, $row->id]) }}" class="!m-0">
                                @csrf
                                <x-button type="submit" variant="ghost" :aria-label="'Archiwizuj: '.$displayName">Archiwizuj</x-button>
                            </form>
                        </div>
                    </x-card>
                </li>
            @endforeach
        </ul>
    @endif

    <div id="supply-search" class="mt-8 scroll-mt-24">
        <h3>Dodaj preparat lub karmę</h3>
        @if ($atLimit)
            <x-alert type="info" role="status">
                Osiągnięto limit aktywnych zapasów w tym zbiorniku ({{ $limit }}). Zarchiwizuj zużytą pozycję, aby dodać nową.
            </x-alert>
        @else
            <form method="GET" action="{{ route('aquarium.tank', $tankId) }}#supply-search" role="search" aria-label="Katalog pielęgnacji" class="flex flex-wrap items-end gap-3 !mt-0">
                <input type="hidden" name="karta" value="zapasy">
                <label for="supply-phrase" class="!m-0 min-w-0 flex-1 basis-48">
                    Nazwa lub ID z obszaru pielęgnacji
                    <input id="supply-phrase" type="search" name="szukaj" value="{{ $phrase }}" maxlength="100" class="min-h-11" required>
                </label>
                <x-button type="submit" variant="secondary">
                    <x-heroicon-o-magnifying-glass class="icon" aria-hidden="true" /> Szukaj
                </x-button>
            </form>

            @if ($addErrors->any())
                <x-alert type="error" role="alert">{{ $addErrors->first() }}</x-alert>
            @endif

            <div aria-live="polite">
                @if ($phrase !== '' && $results === [])
                    <p>Brak wyników.</p>
                @endif
            </div>

            @if ($results !== [])
                <form method="POST" action="{{ route('aquarium.supplies.store', ['szukaj' => $phrase]) }}" class="space-y-4">
                    @csrf
                    <input type="hidden" name="tank" value="{{ $tankId }}">

                    <fieldset @if ($addErrors->has('catalog_id')) aria-describedby="supply-choice-error" @endif>
                        <legend>Wyniki wyszukiwania (pielęgnacja)</legend>
                        <div class="mb-4 space-y-2">
                            @foreach ($results as $entry)
                                <label class="!m-0 flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-line px-3 py-2">
                                    <input type="radio" name="catalog_id" value="{{ $entry->id }}" class="shrink-0" required
                                        @checked(old('catalog_id') === $entry->id)
                                        @if ($addErrors->has('catalog_id')) aria-invalid="true" @endif>
                                    <span class="min-w-0 wrap-break-word">
                                        {{ $entry->name }}
                                        @if (($entry->fields['producent'] ?? '') !== '')
                                            — {{ $entry->fields['producent'] }}
                                        @endif
                                        <span class="text-muted">· {{ $entry->id }}</span>
                                    </span>
                                </label>
                            @endforeach
                        </div>
                        @error('catalog_id', 'supply')
                            <span id="supply-choice-error" role="alert" class="field-error">{{ $message }}</span>
                        @enderror
                    </fieldset>

                    <div class="grid gap-4 sm:grid-cols-2">
                        <label for="supply-unit" class="!m-0">
                            Jednostka
                            <select id="supply-unit" name="unit" class="min-h-11" required>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->value }}" @selected(old('unit', 'ml') === $unit->value)>{{ $unit->label() }}</option>
                                @endforeach
                            </select>
                        </label>

                        <label for="supply-package" class="!m-0">
                            Pojemność / wielkość opakowania
                            <input id="supply-package" type="number" name="package_amount" step="0.01" min="0.01" max="999999" required
                                class="min-h-11" value="{{ is_scalar(old('package_amount')) ? old('package_amount') : '' }}">
                        </label>

                        <label for="supply-remaining" class="!m-0">
                            Pozostała ilość na start (opcjonalnie)
                            <input id="supply-remaining" type="number" name="remaining_amount" step="0.01" min="0" max="999999"
                                class="min-h-11" value="{{ is_scalar(old('remaining_amount')) ? old('remaining_amount') : '' }}"
                                placeholder="domyślnie pełne opakowanie">
                        </label>

                        <label for="supply-dose" class="!m-0">
                            Dawka z etykiety / stała porcja
                            <input id="supply-dose" type="number" name="dose_amount" step="0.01" min="0.01" max="999999" required
                                class="min-h-11" value="{{ is_scalar(old('dose_amount')) ? old('dose_amount') : '' }}">
                        </label>

                        <label for="supply-per-liters" class="!m-0">
                            Na ile litrów (puste = stała porcja, np. karma)
                            <input id="supply-per-liters" type="number" name="dose_per_liters" step="0.01" min="0.01" max="999999"
                                class="min-h-11" value="{{ is_scalar(old('dose_per_liters')) ? old('dose_per_liters') : '' }}">
                        </label>

                        <label for="supply-interval" class="!m-0">
                            Co ile dni (puste = tylko po „Zastosowałem”)
                            <input id="supply-interval" type="number" name="interval_days" step="1" min="1" max="3650"
                                class="min-h-11" value="{{ is_scalar(old('interval_days')) ? old('interval_days') : '' }}">
                        </label>

                        <label for="supply-shop" class="!m-0 sm:col-span-2">
                            Preferowany sklep do ponownego zamówienia (opcjonalnie)
                            <select id="supply-shop" name="preferred_shop_id" class="min-h-11">
                                <option value="">— oferty z okolicy —</option>
                                @foreach ($shopNames as $shopId => $shopName)
                                    <option value="{{ $shopId }}" @selected((string) old('preferred_shop_id') === (string) $shopId)>{{ $shopName }}</option>
                                @endforeach
                            </select>
                        </label>
                    </div>

                    <x-button type="submit">Dodaj zapas</x-button>
                </form>
            @endif
        @endif
    </div>
</div>

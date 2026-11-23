<section
    id="creator-step-3"
    class="creator-step-panel"
    x-show="step === 3"
    aria-labelledby="creator-heading-3"
>
    <p class="eyebrow">Krok 3 z 6</p>
    <h2 id="creator-heading-3" tabindex="-1" x-ref="stepHeading3">Rośliny</h2>
    <p class="creator-step-lead">Przeglądaj rośliny pasujące do wielkości i typu wybranego zbiornika. Ten krok możesz pominąć, jeśli planujesz zbiornik bez roślin.</p>

    <div class="creator-notice">
        <p class="text-sm">
            <strong>Zakres danych katalogu:</strong> Baza wiedzy nie podaje wymagań roślin co do pH, temperatury, KH ani GH (brak danych katalogowych). Dopasowanie ocenia pola WielkośćAkwarium, TypAkwarium i CO₂.
        </p>
    </div>

    <div class="creator-toolbar">
        <div class="creator-search-row">
            <div class="creator-field creator-search-field">
                <label for="creator-flora-search">Szukaj rośliny (możesz wpisywać bez polskich znaków)</label>
                <input
                    id="creator-flora-search"
                    type="search"
                    placeholder="np. anubias, rotala, mech lub FL0001"
                    x-model="floraFilters.query"
                    autocomplete="off"
                >
                <small x-show="isSearchTooShort(floraFilters.query)" x-cloak>
                    Wpisz co najmniej <span class="tabular-nums" x-text="searchMinChars"></span> znaki, aby zawęzić listę.
                </small>
            </div>
            <button type="button" class="creator-pill" x-show="floraFilters.query || floraFilters.plantZone || floraFilters.difficulty || floraFilters.co2" x-cloak @click="resetFloraFilters()">
                Wyczyść filtry
            </button>
        </div>

        <div class="creator-filter-block">
            <span class="creator-filter-label">Strefa sadzenia</span>
            <div class="creator-pill-group" role="group" aria-label="Filtr strefy sadzenia roślin">
                <button type="button" class="creator-pill" :aria-pressed="floraFilters.plantZone === ''" @click="floraFilters.plantZone = ''">Wszystkie</button>
                <template x-for="zone in floraZones" :key="zone">
                    <button type="button" class="creator-pill" :aria-pressed="floraFilters.plantZone === zone" @click="floraFilters.plantZone = floraFilters.plantZone === zone ? '' : zone" x-text="zone"></button>
                </template>
            </div>
        </div>

        <div class="creator-filter-block">
            <span class="creator-filter-label">Poziom trudności</span>
            <div class="creator-pill-group" role="group" aria-label="Filtr trudności uprawy">
                <button type="button" class="creator-pill" :aria-pressed="floraFilters.difficulty === ''" @click="floraFilters.difficulty = ''">Wszystkie</button>
                <template x-for="diff in floraDifficulties" :key="diff">
                    <button type="button" class="creator-pill" :aria-pressed="floraFilters.difficulty === diff" @click="floraFilters.difficulty = floraFilters.difficulty === diff ? '' : diff" x-text="diff"></button>
                </template>
            </div>
        </div>

        <div class="creator-filter-block">
            <span class="creator-filter-label">Zapotrzebowanie na CO₂</span>
            <div class="creator-pill-group" role="group" aria-label="Filtr zapotrzebowania na CO2">
                <button type="button" class="creator-pill" :aria-pressed="floraFilters.co2 === ''" @click="floraFilters.co2 = ''">Wszystkie</button>
                <template x-for="co2Name in (bundle?.dicts?.co2Needs ?? [])" :key="co2Name">
                    <button type="button" class="creator-pill" :aria-pressed="floraFilters.co2 === co2Name" @click="floraFilters.co2 = floraFilters.co2 === co2Name ? '' : co2Name" x-text="co2Name"></button>
                </template>
            </div>
        </div>
    </div>

    <p class="mb-4 text-sm text-muted tabular-nums" x-show="bundle">
        Wyświetlono <strong class="text-ink" x-text="filteredFlora.length"></strong> roślin dla wybranego zbiornika.
    </p>

    <div class="empty-state" x-show="bundle && filteredFlora.length === 0" x-cloak>
        <p class="m-0 font-semibold">Brak roślin spełniających wybrane filtry.</p>
        <button type="button" class="creator-pill mt-3" @click="resetFloraFilters()">Pokaż wszystkie pasujące</button>
    </div>

    <ul class="creator-cards-grid" aria-label="Lista roślin z bazy wiedzy">
        <template x-for="item in filteredFlora" :key="item.id">
            <li class="creator-card" :class="{ 'creator-card-selected': qtyOf('flora', item.id) > 0 }">
                <div class="creator-card-media">
                    <template x-if="item.thumb">
                        <img :src="item.thumb" :alt="item.name" loading="lazy" decoding="async">
                    </template>
                    <template x-if="!item.thumb">
                        <div class="creator-card-placeholder" aria-hidden="true">
                            <x-heroicon-o-photo />
                        </div>
                    </template>
                </div>

                <div class="min-w-0">
                    <h3 class="creator-card-title">
                        <a :href="catalogUrl('flora', item.id)" target="_blank" rel="noopener" x-text="item.name"></a>
                    </h3>
                    <p class="latin-name m-0 text-sm" lang="la" x-show="item.latin" x-text="item.latin"></p>
                </div>

                <ul class="creator-tags">
                    <template x-if="item.plantZone">
                        <li class="creator-tag" x-text="item.plantZone"></li>
                    </template>
                    <template x-if="item.difficulty">
                        <li class="creator-tag" x-text="item.difficulty"></li>
                    </template>
                    <template x-if="co2Label(item.co2)">
                        <li class="creator-tag" x-text="'CO₂: ' + co2Label(item.co2)"></li>
                    </template>
                    <template x-if="item.plantSize">
                        <li class="creator-tag" x-text="item.plantSize"></li>
                    </template>
                </ul>

                <template x-if="co2Label(item.co2) === 'Niezbędny'">
                    <details class="why">
                        <summary>Dlaczego?</summary>
                        <ul>
                            <li x-text="'baza: CO2 = Niezbędny (' + item.id + ')'"></li>
                        </ul>
                    </details>
                </template>

                <div class="creator-card-actions">
                    <template x-if="qtyOf('flora', item.id) === 0">
                        <button type="button" class="creator-add-btn" @click="setQty('flora', item.id, 1)">+ Dodaj</button>
                    </template>
                    <template x-if="qtyOf('flora', item.id) > 0">
                        <div class="creator-qty-stepper">
                            <button
                                type="button"
                                class="creator-qty-btn"
                                :aria-label="'Zmniejsz ilość: ' + item.name"
                                @click="stepQty('flora', item.id, -1)"
                            >−</button>
                            <span class="creator-qty-value tabular-nums" x-text="qtyOf('flora', item.id) + ' szt.'"></span>
                            <button
                                type="button"
                                class="creator-qty-btn"
                                :aria-label="'Zwiększ ilość: ' + item.name"
                                @click="stepQty('flora', item.id, 1)"
                            >+</button>
                        </div>
                    </template>
                    <a class="text-sm" :href="catalogUrl('flora', item.id)" target="_blank" rel="noopener">Szczegóły</a>
                </div>
            </li>
        </template>
    </ul>

    <div class="creator-step-footer">
        <button type="button" class="creator-pill" @click="goToStep(2)">
            <x-heroicon-o-arrow-left class="icon" aria-hidden="true" /> Wstecz: Ryby i bezkręgowce
        </button>
        <button type="button" class="creator-add-btn" @click="goToStep(4)">
            Dalej: Sprzęt <x-heroicon-o-arrow-right class="icon" aria-hidden="true" />
        </button>
    </div>
</section>

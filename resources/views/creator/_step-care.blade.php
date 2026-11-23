<section
    id="creator-step-5"
    class="creator-step-panel"
    x-show="step === 5"
    aria-labelledby="creator-heading-5"
>
    <p class="eyebrow">Krok 5 z 6</p>
    <h2 id="creator-heading-5" tabindex="-1" x-ref="stepHeading5">Pielęgnacja</h2>
    <p class="creator-step-lead">Opcjonalnie wybierz produkty startowe, pokarmy, nawozy i testy wody. Nie narzucamy dawek — dawkowanie sprawdzaj zawsze na etykiecie producenta.</p>

    <div class="creator-notice creator-notice-warn" role="status" aria-live="polite" x-show="careResult.snailFoodMissing" x-cloak>
        <p class="m-0 text-sm font-semibold" x-text="careResult.snailFoodNote || 'Pokarmy dla ślimaków: brak danych w bazie.'">
            Pokarmy dla ślimaków: brak danych w bazie.
        </p>
    </div>

    <div class="creator-toolbar">
        <div class="creator-filter-block">
            <span class="creator-filter-label">Grupa produktów</span>
            <div class="creator-pill-group" role="group" aria-label="Grupy produktów pielęgnacyjnych">
                <button type="button" class="creator-pill" :aria-pressed="careFilters.group === 'start'" @click="careFilters.group = 'start'">
                    Start / przygotowanie <span class="creator-tag tabular-nums" x-text="(careResult.start ?? []).length"></span>
                </button>
                <button type="button" class="creator-pill" :aria-pressed="careFilters.group === 'feeding'" @click="careFilters.group = 'feeding'">
                    Pokarmy <span class="creator-tag tabular-nums" x-text="(careResult.feeding?.items ?? []).length"></span>
                </button>
                <button type="button" class="creator-pill" :aria-pressed="careFilters.group === 'plants'" @click="careFilters.group = 'plants'">
                    Dla roślin <span class="creator-tag tabular-nums" x-text="(careResult.plants ?? []).length"></span>
                </button>
                <button type="button" class="creator-pill" :aria-pressed="careFilters.group === 'testing'" @click="careFilters.group = 'testing'">
                    Testy wody <span class="creator-tag tabular-nums" x-text="(careResult.testing ?? []).length"></span>
                </button>
                <button type="button" class="creator-pill" :aria-pressed="careFilters.group === 'all'" @click="careFilters.group = 'all'">
                    Wszystkie z katalogu <span class="creator-tag tabular-nums" x-text="(bundle?.care ?? []).length"></span>
                </button>
            </div>
        </div>

        <div class="creator-search-row">
            <div class="creator-field creator-search-field">
                <label for="creator-care-search">Szukaj preparatu lub pokarmu (możesz wpisywać bez polskich znaków)</label>
                <input
                    id="creator-care-search"
                    type="search"
                    placeholder="np. bakterie, test ph, nawoz lub CH0001"
                    x-model="careFilters.query"
                    autocomplete="off"
                >
                <small x-show="isSearchTooShort(careFilters.query)" x-cloak>
                    Wpisz co najmniej <span class="tabular-nums" x-text="searchMinChars"></span> znaki, aby zawęzić listę.
                </small>
            </div>
            <button type="button" class="creator-pill" x-show="careFilters.query !== ''" x-cloak @click="careFilters.query = ''">
                Wyczyść wyszukiwanie
            </button>
        </div>
    </div>

    <div class="empty-state" x-show="bundle && filteredCareItems.length === 0" x-cloak>
        <template x-if="careFilters.group === 'feeding' && (careResult.feeding?.items ?? []).length === 0">
            <div>
                <p class="m-0 font-semibold">Brak przypisanych pokarmów dla obecnej obsady.</p>
                <p class="mt-2 text-sm" x-show="careResult.snailFoodMissing">Pokarmy dla ślimaków: brak danych w bazie.</p>
                <p class="mt-2 text-sm" x-show="!careResult.snailFoodMissing">Dodaj ryby lub skorupiaki w kroku 2 albo przełącz widok na „Wszystkie z katalogu”.</p>
            </div>
        </template>
        <template x-if="careFilters.group === 'plants' && (careResult.plants ?? []).length === 0">
            <p class="m-0 font-semibold">Dodaj rośliny w kroku 3, aby zobaczyć nawozy i preparaty roślinne, albo przełącz widok na „Wszystkie z katalogu”.</p>
        </template>
        <template x-if="careFilters.group !== 'feeding' && careFilters.group !== 'plants' || ((careFilters.group === 'feeding' && (careResult.feeding?.items ?? []).length > 0) || (careFilters.group === 'plants' && (careResult.plants ?? []).length > 0))">
            <p class="m-0 font-semibold">Brak pozycji spełniających kryteria wyszukiwania.</p>
        </template>
    </div>

    <ul class="creator-cards-grid" aria-label="Lista produktów do pielęgnacji z bazy wiedzy">
        <template x-for="item in filteredCareItems" :key="item.id">
            <li class="creator-card" :class="{ 'creator-card-selected': qtyOf('care', item.id) > 0 }">
                <div class="creator-card-media">
                    <template x-if="item.thumb">
                        <img :src="item.thumb" :alt="item.name" loading="lazy" decoding="async">
                    </template>
                    <template x-if="!item.thumb">
                        <div class="creator-card-placeholder" aria-hidden="true">
                            <x-heroicon-o-beaker />
                        </div>
                    </template>
                </div>

                <div class="min-w-0">
                    <h3 class="creator-card-title">
                        <a :href="catalogUrl('care', item.id)" target="_blank" rel="noopener" x-text="item.name"></a>
                    </h3>
                    <p class="m-0 text-sm text-muted" x-show="item.brand || item.producer" x-text="[item.brand, item.producer].filter(Boolean).join(' · ')"></p>
                </div>

                <ul class="creator-tags">
                    <template x-if="item.domain">
                        <li class="creator-tag" x-text="item.domain"></li>
                    </template>
                    <template x-if="item.taskKind">
                        <li class="creator-tag" x-text="item.taskKind"></li>
                    </template>
                </ul>

                <p class="m-0 text-sm text-text-2" x-show="item.desc" x-text="item.desc"></p>

                <div class="creator-card-actions">
                    <template x-if="qtyOf('care', item.id) === 0">
                        <button type="button" class="creator-add-btn" @click="setQty('care', item.id, 1)">+ Dodaj</button>
                    </template>
                    <template x-if="qtyOf('care', item.id) > 0">
                        <div class="creator-qty-stepper">
                            <button
                                type="button"
                                class="creator-qty-btn"
                                :aria-label="'Zmniejsz ilość: ' + item.name"
                                @click="stepQty('care', item.id, -1)"
                            >−</button>
                            <span class="creator-qty-value tabular-nums" x-text="qtyOf('care', item.id) + ' szt.'"></span>
                            <button
                                type="button"
                                class="creator-qty-btn"
                                :aria-label="'Zwiększ ilość: ' + item.name"
                                @click="stepQty('care', item.id, 1)"
                            >+</button>
                        </div>
                    </template>
                    <a class="text-sm" :href="catalogUrl('care', item.id)" target="_blank" rel="noopener">Szczegóły</a>
                </div>
            </li>
        </template>
    </ul>

    <div class="creator-step-footer">
        <button type="button" class="creator-pill" @click="goToStep(4)">
            <x-heroicon-o-arrow-left class="icon" aria-hidden="true" /> Wstecz: Sprzęt
        </button>
        <button type="button" class="creator-add-btn" @click="goToStep(6)">
            Dalej: Lista zakupów <x-heroicon-o-arrow-right class="icon" aria-hidden="true" />
        </button>
    </div>
</section>

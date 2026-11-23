<section
    id="creator-step-4"
    class="creator-step-panel"
    x-show="step === 4"
    aria-labelledby="creator-heading-4"
>
    <p class="eyebrow">Krok 4 z 6</p>
    <h2 id="creator-heading-4" tabindex="-1" x-ref="stepHeading4">Sprzęt</h2>
    <p class="creator-step-lead">Przeglądaj urządzenia dopasowane w katalogu do wielkości i typu wybranego zbiornika. Sam decydujesz, które pozycje dodać do listy.</p>

    <div class="creator-notice">
        <p class="text-sm font-semibold text-ink" x-text="equipmentResult.missingSpecsNote || 'Moc i przepływ: brak danych w bazie — sprawdź na opakowaniu.'">
            Moc i przepływ: brak danych w bazie — sprawdź na opakowaniu.
        </p>
    </div>

    <div class="creator-toolbar">
        <div class="creator-filter-block">
            <span class="creator-filter-label">Kategoria sprzętu</span>
            <div class="creator-pill-group" role="group" aria-label="Kategorie sprzętu">
                <template x-for="cat in (equipmentResult.categories ?? [])" :key="cat.category">
                    <button
                        type="button"
                        class="creator-pill"
                        :aria-pressed="equipmentFilters.category === cat.category"
                        @click="equipmentFilters.category = cat.category"
                    >
                        <span x-text="cat.category"></span>
                        <span class="creator-tag tabular-nums" x-text="cat.items.length"></span>
                    </button>
                </template>
            </div>
        </div>

        <div class="creator-search-row">
            <div class="creator-field creator-search-field">
                <label for="creator-equipment-search">Szukaj w kategorii (możesz wpisywać bez polskich znaków)</label>
                <input
                    id="creator-equipment-search"
                    type="search"
                    placeholder="np. kaskadowy, aquael, grzalka lub SP0001"
                    x-model="equipmentFilters.query"
                    autocomplete="off"
                >
                <small x-show="isSearchTooShort(equipmentFilters.query)" x-cloak>
                    Wpisz co najmniej <span class="tabular-nums" x-text="searchMinChars"></span> znaki, aby zawęzić listę.
                </small>
            </div>
            <button type="button" class="creator-pill" x-show="equipmentFilters.query !== ''" x-cloak @click="equipmentFilters.query = ''">
                Wyczyść wyszukiwanie
            </button>
        </div>
    </div>

    <template x-if="activeEquipmentCategory?.hint">
        <div class="creator-compat-box" role="status" aria-live="polite">
            <p class="m-0 text-sm font-semibold text-ink">
                Wskazówka dla kategorii <span x-text="activeEquipmentCategory.category"></span>:
                <span x-text="activeEquipmentCategory.hint.text"></span>
            </p>
            <details class="why" x-show="(activeEquipmentCategory.hint.evidence ?? []).length > 0">
                <summary>Dlaczego?</summary>
                <ul>
                    <template x-for="(ev, idx) in (activeEquipmentCategory.hint.evidence ?? [])" :key="idx">
                        <li>
                            <span x-text="ev.text"></span>
                            <template x-if="ev.batch !== null">
                                <small x-text="'(partia bazy ' + ev.batch + ')'"></small>
                            </template>
                        </li>
                    </template>
                </ul>
            </details>
        </div>
    </template>

    <div class="empty-state" x-show="bundle && filteredEquipmentItems.length === 0" x-cloak>
        <p class="m-0 font-semibold">Brak pozycji w tej kategorii dla wybranego zbiornika i frazy.</p>
    </div>

    <ul class="creator-cards-grid" aria-label="Lista sprzętu z bazy wiedzy">
        <template x-for="item in filteredEquipmentItems" :key="item.id">
            <li class="creator-card" :class="{ 'creator-card-selected': qtyOf('equipment', item.id) > 0 }">
                <div class="creator-card-media">
                    <template x-if="item.thumb">
                        <img :src="item.thumb" :alt="item.name" loading="lazy" decoding="async">
                    </template>
                    <template x-if="!item.thumb">
                        <div class="creator-card-placeholder" aria-hidden="true">
                            <x-heroicon-o-wrench-screwdriver />
                        </div>
                    </template>
                </div>

                <div class="min-w-0">
                    <h3 class="creator-card-title">
                        <a :href="catalogUrl('equipment', item.id)" target="_blank" rel="noopener" x-text="item.name"></a>
                    </h3>
                    <p class="m-0 text-sm text-muted" x-show="item.brand || item.producer" x-text="[item.brand, item.producer].filter(Boolean).join(' · ')"></p>
                </div>

                <ul class="creator-tags">
                    <template x-if="item.kind">
                        <li class="creator-tag" x-text="item.kind"></li>
                    </template>
                    <template x-if="item.level">
                        <li class="creator-tag" x-text="item.level"></li>
                    </template>
                </ul>

                <p class="m-0 text-sm text-text-2" x-show="item.shortDesc" x-text="item.shortDesc"></p>

                <div class="creator-card-actions">
                    <template x-if="qtyOf('equipment', item.id) === 0">
                        <button type="button" class="creator-add-btn" @click="setQty('equipment', item.id, 1)">+ Dodaj</button>
                    </template>
                    <template x-if="qtyOf('equipment', item.id) > 0">
                        <div class="creator-qty-stepper">
                            <button
                                type="button"
                                class="creator-qty-btn"
                                :aria-label="'Zmniejsz ilość: ' + item.name"
                                @click="stepQty('equipment', item.id, -1)"
                            >−</button>
                            <span class="creator-qty-value tabular-nums" x-text="qtyOf('equipment', item.id) + ' szt.'"></span>
                            <button
                                type="button"
                                class="creator-qty-btn"
                                :aria-label="'Zwiększ ilość: ' + item.name"
                                @click="stepQty('equipment', item.id, 1)"
                            >+</button>
                        </div>
                    </template>
                    <a class="text-sm" :href="catalogUrl('equipment', item.id)" target="_blank" rel="noopener">Szczegóły</a>
                </div>
            </li>
        </template>
    </ul>

    <div class="creator-step-footer">
        <button type="button" class="creator-pill" @click="goToStep(3)">
            <x-heroicon-o-arrow-left class="icon" aria-hidden="true" /> Wstecz: Rośliny
        </button>
        <button type="button" class="creator-add-btn" @click="goToStep(5)">
            Dalej: Pielęgnacja <x-heroicon-o-arrow-right class="icon" aria-hidden="true" />
        </button>
    </div>
</section>

<section
    id="creator-step-2"
    class="creator-step-panel"
    x-show="step === 2"
    aria-labelledby="creator-heading-2"
>
    <p class="eyebrow">Krok 2 z 6</p>
    <h2 id="creator-heading-2" tabindex="-1" x-ref="stepHeading2">Ryby i bezkręgowce</h2>
    <p class="creator-step-lead">Gatunki są ułożone według dopasowania do Twojego zbiornika. Temperament pokazuje wyłącznie etykietę gatunku — relacja pary: brak danych.</p>

    <div class="creator-compat-box" role="status" aria-live="polite" x-show="selectedCountForArea('fauna') > 0 || selectedCountForArea('flora') > 0" x-cloak>
        <div class="flex flex-wrap items-center justify-between gap-2">
            <h3 class="m-0 text-base font-bold text-ink">Porównanie obsady z bazą wiedzy</h3>
            <span class="creator-tag" x-text="compatibilityVerdictLabel"></span>
        </div>

        <p class="mt-2 text-sm text-text-2" x-show="temperamentFindingsCount > 0">
            Relacja pary: brak danych.
        </p>

        <ul class="creator-compat-list" x-show="visibleFindings.length > 0">
            <template x-for="finding in visibleFindings" :key="finding.key">
                <li class="creator-compat-item">
                    <p class="m-0 text-sm">
                        <strong x-text="severityLabel(finding.severity) + '.'"></strong>
                        <span x-text="finding.text"></span>
                    </p>
                    <details class="why" x-show="(finding.evidence ?? []).length > 0">
                        <summary>Dlaczego?</summary>
                        <ul>
                            <template x-for="(ev, idx) in (finding.evidence ?? [])" :key="idx">
                                <li>
                                    <span x-text="ev.text"></span>
                                    <template x-if="ev.batch !== null">
                                        <small x-text="'(partia bazy ' + ev.batch + ')'"></small>
                                    </template>
                                </li>
                            </template>
                        </ul>
                    </details>
                </li>
            </template>
        </ul>

        <ul class="creator-compat-list" x-show="(compatibility.discrepancies ?? []).length > 0">
            <template x-for="disc in (compatibility.discrepancies ?? [])" :key="disc.id + ':' + disc.column">
                <li class="creator-compat-item">
                    <p class="m-0 text-sm">
                        <strong>Rozbieżność danych.</strong>
                        <span x-text="disc.text"></span>
                    </p>
                    <details class="why" x-show="(disc.evidence ?? []).length > 0">
                        <summary>Dlaczego?</summary>
                        <ul>
                            <template x-for="(ev, idx) in (disc.evidence ?? [])" :key="idx">
                                <li>
                                    <span x-text="ev.text"></span>
                                    <template x-if="ev.batch !== null">
                                        <small x-text="'(partia bazy ' + ev.batch + ')'"></small>
                                    </template>
                                </li>
                            </template>
                        </ul>
                    </details>
                </li>
            </template>
        </ul>

        <details class="mt-3" x-show="(compatibility.rows ?? []).length > 0">
            <summary class="min-h-11 cursor-pointer py-2 text-sm font-semibold text-aqua-700">Zestawienie porównań z danymi i bez danych</summary>
            <div class="table-scroll">
                <table>
                    <caption class="sr-only">Kontrole zgodności obsady</caption>
                    <thead>
                        <tr>
                            <th scope="col">Kontrola</th>
                            <th scope="col">Z danymi</th>
                            <th scope="col">Brak danych</th>
                        </tr>
                    </thead>
                    <tbody>
                        <template x-for="row in (compatibility.rows ?? [])" :key="row.check">
                            <tr>
                                <th scope="row" x-text="checkLabel(row.check)"></th>
                                <td class="tabular-nums" x-text="row.withData + ' ' + checkUnit(row.check)"></td>
                                <td class="tabular-nums" x-text="row.missingData + ' ' + checkUnit(row.check)"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </details>
    </div>

    <div class="creator-toolbar">
        <div class="creator-search-row">
            <div class="creator-field creator-search-field">
                <label for="creator-fauna-search">Szukaj gatunku (możesz wpisywać bez polskich znaków)</label>
                <input
                    id="creator-fauna-search"
                    type="search"
                    placeholder="np. neon, kirysek, gupik lub FN0001"
                    x-model="faunaFilters.query"
                    autocomplete="off"
                >
                <small x-show="isSearchTooShort(faunaFilters.query)" x-cloak>
                    Wpisz co najmniej <span class="tabular-nums" x-text="searchMinChars"></span> znaki, aby zawęzić listę.
                </small>
            </div>
            <button type="button" class="creator-pill" x-show="faunaFilters.query || faunaFilters.zone !== null || faunaFilters.kind !== null || faunaFilters.compatOnly" x-cloak @click="resetFaunaFilters()">
                Wyczyść filtry
            </button>
        </div>

        <div class="creator-filter-block">
            <span class="creator-filter-label">Strefa pływania</span>
            <div class="creator-pill-group" role="group" aria-label="Filtr strefy pływania">
                <button type="button" class="creator-pill" :aria-pressed="faunaFilters.zone === null" @click="faunaFilters.zone = null">Wszystkie</button>
                <button type="button" class="creator-pill" :aria-pressed="faunaFilters.zone === 2" @click="faunaFilters.zone = faunaFilters.zone === 2 ? null : 2">Górna</button>
                <button type="button" class="creator-pill" :aria-pressed="faunaFilters.zone === 1" @click="faunaFilters.zone = faunaFilters.zone === 1 ? null : 1">Środkowa</button>
                <button type="button" class="creator-pill" :aria-pressed="faunaFilters.zone === 0" @click="faunaFilters.zone = faunaFilters.zone === 0 ? null : 0">Dolna</button>
            </div>
        </div>

        <div class="creator-filter-block">
            <span class="creator-filter-label">Rodzaj</span>
            <div class="creator-pill-group" role="group" aria-label="Filtr rodzaju fauny">
                <button type="button" class="creator-pill" :aria-pressed="faunaFilters.kind === null" @click="faunaFilters.kind = null">Wszystkie</button>
                <button type="button" class="creator-pill" :aria-pressed="faunaFilters.kind === 'Ryba'" @click="faunaFilters.kind = faunaFilters.kind === 'Ryba' ? null : 'Ryba'">Ryba</button>
                <button type="button" class="creator-pill" :aria-pressed="faunaFilters.kind === 'Skorupiak'" @click="faunaFilters.kind = faunaFilters.kind === 'Skorupiak' ? null : 'Skorupiak'">Skorupiak</button>
                <button type="button" class="creator-pill" :aria-pressed="faunaFilters.kind === 'Ślimak'" @click="faunaFilters.kind = faunaFilters.kind === 'Ślimak' ? null : 'Ślimak'">Ślimak</button>
            </div>
        </div>

        <div class="creator-filter-block">
            <span class="creator-filter-label">Zgodność z wybraną obsadą</span>
            <div class="creator-pill-group" role="group" aria-label="Filtr zgodności z obsadą">
                <button type="button" class="creator-pill" :aria-pressed="!faunaFilters.compatOnly" @click="faunaFilters.compatOnly = false">Pokaż wszystkie</button>
                <button type="button" class="creator-pill" :aria-pressed="faunaFilters.compatOnly" @click="faunaFilters.compatOnly = !faunaFilters.compatOnly">Tylko bez uwag katalogowych</button>
            </div>
        </div>
    </div>

    <p class="mb-4 text-sm text-muted tabular-nums" x-show="bundle">
        Wyświetlono <strong class="text-ink" x-text="filteredFauna.length"></strong> gatunków (pominięto w rankingu dla tego profilu: <span x-text="faunaRanking.excluded"></span>).
    </p>

    <div class="empty-state" x-show="bundle && filteredFauna.length === 0" x-cloak>
        <p class="m-0 font-semibold">Brak gatunków spełniających wybrane filtry.</p>
        <button type="button" class="creator-pill mt-3" @click="resetFaunaFilters()">Pokaż wszystkie pasujące</button>
    </div>

    <ul class="creator-cards-grid" aria-label="Lista fauny z bazy wiedzy">
        <template x-for="item in filteredFauna" :key="item.id">
            <li class="creator-card" :class="{ 'creator-card-selected': qtyOf('fauna', item.id) > 0 }">
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

                <div class="creator-card-top">
                    <div class="min-w-0">
                        <h3 class="creator-card-title">
                            <a :href="catalogUrl('fauna', item.id)" target="_blank" rel="noopener" x-text="item.name"></a>
                        </h3>
                        <p class="latin-name m-0 text-sm" lang="la" x-show="item.latin" x-text="item.latin"></p>
                    </div>
                    <span class="creator-fit-badge tabular-nums" x-text="item.percent + '%'"></span>
                </div>

                <ul class="creator-tags">
                    <li class="creator-tag" x-text="item.kind"></li>
                    <template x-if="zoneLabel(item.zone)">
                        <li class="creator-tag" x-text="'Strefa: ' + zoneLabel(item.zone)"></li>
                    </template>
                    <template x-if="tempRangeLabel(item.temp)">
                        <li class="creator-tag tabular-nums" x-text="tempRangeLabel(item.temp)"></li>
                    </template>
                    <template x-if="temperamentLabel(item.temperament)">
                        <li class="creator-tag" x-text="'Temperament: ' + temperamentLabel(item.temperament)"></li>
                    </template>
                </ul>

                <template x-if="candidateNote(item.id).hasWarning">
                    <div class="text-sm">
                        <span class="creator-tag creator-tag-warn">Uwaga katalogowa</span>
                        <details class="why">
                            <summary>Dlaczego?</summary>
                            <ul>
                                <template x-for="(f, idx) in candidateNote(item.id).findings" :key="'f-' + idx">
                                    <li>
                                        <span x-text="f.text"></span>
                                        <template x-for="(ev, eIdx) in (f.evidence ?? [])" :key="'ev-' + eIdx">
                                            <span class="block text-xs text-muted" x-text="ev.text"></span>
                                        </template>
                                    </li>
                                </template>
                                <template x-for="(d, idx) in candidateNote(item.id).discrepancies" :key="'d-' + idx">
                                    <li x-text="d.text"></li>
                                </template>
                            </ul>
                        </details>
                    </div>
                </template>

                <div class="creator-card-actions">
                    <template x-if="qtyOf('fauna', item.id) === 0">
                        <button type="button" class="creator-add-btn" @click="setQty('fauna', item.id, 1)">+ Dodaj</button>
                    </template>
                    <template x-if="qtyOf('fauna', item.id) > 0">
                        <div class="creator-qty-stepper">
                            <button
                                type="button"
                                class="creator-qty-btn"
                                :aria-label="'Zmniejsz ilość: ' + item.name"
                                @click="stepQty('fauna', item.id, -1)"
                            >−</button>
                            <span class="creator-qty-value tabular-nums" x-text="qtyOf('fauna', item.id) + ' szt.'"></span>
                            <button
                                type="button"
                                class="creator-qty-btn"
                                :aria-label="'Zwiększ ilość: ' + item.name"
                                @click="stepQty('fauna', item.id, 1)"
                            >+</button>
                        </div>
                    </template>
                    <a class="text-sm" :href="catalogUrl('fauna', item.id)" target="_blank" rel="noopener">Szczegóły</a>
                </div>
            </li>
        </template>
    </ul>

    <div class="creator-step-footer">
        <button type="button" class="creator-pill" @click="goToStep(1)">
            <x-heroicon-o-arrow-left class="icon" aria-hidden="true" /> Wstecz: Zbiornik
        </button>
        <button type="button" class="creator-add-btn" @click="goToStep(3)">
            Dalej: Rośliny <x-heroicon-o-arrow-right class="icon" aria-hidden="true" />
        </button>
    </div>
</section>

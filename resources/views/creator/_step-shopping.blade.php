<section
    id="creator-step-6"
    class="creator-step-panel"
    x-show="step === 6"
    aria-labelledby="creator-heading-6"
>
    <p class="eyebrow">Krok 6 z 6</p>
    <h2 id="creator-heading-6" tabindex="-1" x-ref="stepHeading6">Lista zakupów i podsumowanie</h2>
    <p class="creator-step-lead">Sprawdź kompletny zestaw wybrany w krokach 1–5. Szkic jest zapisany lokalnie w tej przeglądarce.</p>

    <div class="creator-notice">
        <dl class="!m-0">
            <div>
                <dt>Zbiornik</dt>
                <dd class="tabular-nums" x-text="displayVolumeText"></dd>
            </div>
            <div>
                <dt>Typ akwarium</dt>
                <dd x-text="tank.type"></dd>
            </div>
            <div>
                <dt>Pozycji na liście</dt>
                <dd class="tabular-nums" x-text="totalSelectedSpecies + ' poz. (' + totalSelectedUnits + ' szt.)'"></dd>
            </div>
            <div>
                <dt>Zgodność</dt>
                <dd x-text="compatibilityVerdictLabel"></dd>
            </div>
        </dl>
    </div>

    <div class="creator-section-block">
        <div class="creator-field max-w-md">
            <label for="creator-step6-plan-name">Nazwa planu</label>
            <input
                id="creator-step6-plan-name"
                type="text"
                maxlength="100"
                placeholder="np. Akwarium w salonie"
                x-model="planName"
                @input="scheduleSave()"
            >
        </div>
    </div>

    <div class="creator-section-block">
        <h3 class="creator-section-title">Wybrane pozycje</h3>

        <div class="empty-state" x-show="items.length === 0" x-cloak>
            <p class="m-0 font-semibold">Twoja lista jest jeszcze pusta.</p>
            <p class="mt-2 text-sm">Przejdź do kroku 2, 3, 4 lub 5, aby dodać wybrane gatunki, rośliny, sprzęt albo preparaty.</p>
            <button type="button" class="creator-add-btn mt-3" @click="goToStep(2)">Przejdź do wyboru fauny</button>
        </div>

        <template x-for="group in groupedSelectedItems" :key="group.area">
            <div class="creator-summary-group">
                <h4 class="text-base font-bold text-ink" x-text="group.label + ' (' + group.items.length + ')'"></h4>
                <ul class="creator-summary-list">
                    <template x-for="item in group.items" :key="item.area + ':' + item.id">
                        <li class="creator-summary-item">
                            <div class="min-w-0 flex-1">
                                <p class="m-0 font-semibold text-ink">
                                    <template x-if="item.known">
                                        <a :href="catalogUrl(item.area, item.id)" target="_blank" rel="noopener" x-text="item.name"></a>
                                    </template>
                                    <template x-if="!item.known">
                                        <span x-text="item.name"></span>
                                    </template>
                                </p>
                                <p class="latin-name m-0 text-sm" lang="la" x-show="item.latin" x-text="item.latin"></p>
                                <p class="m-0 text-sm" :class="item.known ? 'text-muted' : 'text-danger font-semibold'" x-text="item.meta"></p>
                            </div>

                            <div class="flex items-center gap-2">
                                <div class="creator-qty-stepper">
                                    <button
                                        type="button"
                                        class="creator-qty-btn"
                                        :aria-label="'Zmniejsz ilość: ' + item.name"
                                        @click="stepQty(item.area, item.id, -1)"
                                    >−</button>
                                    <span class="creator-qty-value tabular-nums" x-text="item.qty + ' szt.'"></span>
                                    <button
                                        type="button"
                                        class="creator-qty-btn"
                                        :aria-label="'Zwiększ ilość: ' + item.name"
                                        @click="stepQty(item.area, item.id, 1)"
                                    >+</button>
                                </div>
                                <button
                                    type="button"
                                    class="creator-pill"
                                    :aria-label="'Usuń z listy: ' + item.name"
                                    @click="setQty(item.area, item.id, 0)"
                                >Usuń</button>
                            </div>
                        </li>
                    </template>
                </ul>
            </div>
        </template>
    </div>

    <div class="creator-section-block">
        <div class="creator-compat-box">
            <h3 class="m-0 text-base font-bold text-ink">Oferty lokalnych sklepów i zapis na koncie</h3>
            <p class="mt-2 mb-0 text-sm text-text-2">
                W tym kroku połączysz ułożoną listę zakupów z ofertami sklepów w wybranym mieście oraz zapiszesz plan na swoim koncie Aikwarium.
            </p>
        </div>
    </div>

    <div class="creator-step-footer">
        <button type="button" class="creator-pill" @click="goToStep(5)">
            <x-heroicon-o-arrow-left class="icon" aria-hidden="true" /> Wstecz: Pielęgnacja
        </button>
        <button type="button" class="creator-pill" @click="goToStep(1)">
            Wróć do kroku 1
        </button>
    </div>
</section>

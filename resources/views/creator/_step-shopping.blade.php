<section
    id="creator-step-6"
    class="creator-step-panel"
    x-show="step === 6"
    aria-labelledby="creator-heading-6"
>
    <p class="eyebrow">Krok 6 z 6</p>
    <h2 id="creator-heading-6" tabindex="-1" x-ref="stepHeading6">Lista zakupów i zapis planu</h2>
    <p class="creator-step-lead">Sprawdź zestaw z kroków 1–5, porównaj oferty sklepów w wybranym mieście i zapisz plan na koncie, aby zamienić go w zbiornik.</p>

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
                :aria-invalid="Boolean(saveError && planName.trim() === '')"
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

    {{-- Oferty lokalnych sklepów --}}
    <div class="creator-section-block" aria-labelledby="creator-offers-heading">
        <h3 id="creator-offers-heading" class="creator-section-title">Oferty lokalnych sklepów</h3>

        <template x-if="!config.isAuthenticated && !config.guestOffersAllowed">
            <div class="creator-compat-box">
                <p class="m-0 font-semibold text-ink">Oferty sklepów dostępne po założeniu bezpłatnego konta Tank</p>
                <p class="mt-2 mb-0 text-sm text-text-2">
                    <span x-text="config.offersDeniedMessage || 'Ta funkcja wymaga zalogowania. Załóż bezpłatne konto Tank.'"></span>
                    Twój szkic jest zapisany w tej przeglądarce i pozostanie dostępny po zalogowaniu.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a :href="config.registerUrl" class="creator-add-btn no-underline">Załóż bezpłatne konto Tank</a>
                    <a :href="config.loginUrl" class="creator-pill no-underline">Zaloguj się</a>
                </div>
            </div>
        </template>

        <template x-if="config.isAuthenticated && !config.canOffers">
            <div class="creator-notice creator-notice-warn" role="alert">
                <p class="m-0 text-sm font-semibold" x-text="config.offersDeniedMessage || 'Oferty sklepów są obecnie niedostępne dla Twojego konta.'"></p>
            </div>
        </template>

        <template x-if="canViewOffers">
            <div>
                <div class="creator-toolbar">
                    <div class="creator-search-row">
                        <div class="creator-field creator-search-field">
                            <label for="creator-offers-city">Miasto odbioru</label>
                            <select
                                id="creator-offers-city"
                                x-model="offersCity"
                                @focus="loadCities()"
                                @change="if (items.length > 0 && (config.isAuthenticated || offersCity !== '')) fetchOffers()"
                            >
                                <option value="" x-text="config.isAuthenticated ? 'Wszystkie miasta (brak zawężenia)' : 'Wybierz miasto…'"></option>
                                <template x-for="cityOption in offersCitiesList" :key="cityOption">
                                    <option :value="cityOption" x-text="cityOption"></option>
                                </template>
                            </select>
                            <small x-show="config.isAuthenticated && !config.userCity">
                                Możesz też zapisać stałe miasto na stałe w <a :href="config.accountUrl">ustawieniach konta</a>.
                            </small>
                        </div>
                        <button
                            type="button"
                            class="creator-add-btn"
                            :disabled="offersLoading || items.length === 0"
                            @click="fetchOffers()"
                        >
                            <span x-text="offersLoading ? 'Sprawdzanie ofert…' : 'Sprawdź oferty'"></span>
                        </button>
                    </div>
                </div>

                <div role="status" aria-live="polite">
                    <div class="creator-notice" x-show="offersLoading" x-cloak>
                        <p class="m-0 text-sm">Pobieranie aktualnych ofert placówek…</p>
                    </div>

                    <div class="creator-notice" x-show="offersStale && !offersLoading" x-cloak>
                        <p class="m-0 text-sm">Lista pozycji zmieniła się od ostatniego sprawdzenia ofert. Kliknij „Sprawdź oferty”, aby odświeżyć podział.</p>
                    </div>
                </div>

                <div class="creator-notice creator-notice-warn" role="alert" x-show="offersError" x-cloak>
                    <p class="m-0 text-sm font-semibold" x-text="offersError"></p>
                </div>

                <div class="creator-notice" role="status" aria-live="polite" x-show="cartFeedback" x-cloak>
                    <p class="m-0 text-sm font-semibold text-ink" x-text="cartFeedback?.message"></p>
                    <div class="mt-3 flex flex-wrap gap-2" x-show="cartFeedback?.cartUrl">
                        <a :href="cartFeedback?.cartUrl" class="creator-add-btn">Przejdź do koszyka</a>
                    </div>
                </div>

                <div class="creator-notice creator-notice-warn" role="alert" x-show="cartError" x-cloak>
                    <p class="m-0 text-sm font-semibold" x-text="cartError"></p>
                </div>

                <template x-if="offersResult">
                    <div class="space-y-4">
                        <p class="m-0 text-sm text-text-2" x-show="offersResult.city">
                            Pokazujemy oferty placówek z miasta: <strong class="text-ink" x-text="offersResult.city"></strong>, ułożone według pokrycia listy i najniższej szacunkowej ceny.
                        </p>
                        <p class="m-0 text-sm text-text-2" x-show="!offersResult.city">
                            Pokazujemy oferty ze wszystkich miast, ułożone według pokrycia listy i najniższej szacunkowej ceny.
                        </p>

                        <div class="empty-state" x-show="(offersResult.groups ?? []).length === 0 && (offersResult.missing ?? []).length === 0">
                            <p class="m-0 font-semibold">Dodaj pozycje do listy, aby sprawdzić oferty sklepów.</p>
                        </div>

                        <template x-for="group in (offersResult.groups ?? [])" :key="group.locationId">
                            <article class="creator-compat-item space-y-3" :aria-label="'Placówka: ' + group.shop + ' — ' + group.location">
                                <div class="flex flex-wrap items-start justify-between gap-3">
                                    <div>
                                        <h4 class="m-0 text-base font-bold text-ink" x-text="group.shop + ' — ' + group.location"></h4>
                                        <p class="m-0 text-sm text-muted" x-text="group.address + ', ' + group.city"></p>
                                        <p class="mt-1 mb-0 text-xs text-muted">
                                            <span class="font-semibold text-ink" x-text="group.shopRatingLabel"></span>
                                            · Opinie wyłącznie po odebranym zamówieniu.
                                            <a :href="group.shopReviewsUrl" target="_blank" rel="noopener">Zobacz opinie</a>
                                        </p>
                                    </div>
                                    <div class="text-right">
                                        <span class="creator-fit-badge tabular-nums" x-text="'Pokrycie: ' + group.coveredCount + ' z ' + totalSelectedSpecies + ' poz.'"></span>
                                        <p class="mt-1 mb-0 text-sm font-bold text-ink tabular-nums" x-text="'Suma dostępnych: ' + group.totalFormatted"></p>
                                    </div>
                                </div>

                                <div class="table-scroll">
                                    <table>
                                        <caption class="sr-only" x-text="'Oferty placówki ' + group.shop + ' — ' + group.location"></caption>
                                        <thead>
                                            <tr>
                                                <th scope="col">Pozycja</th>
                                                <th scope="col">Wariant</th>
                                                <th scope="col">Ilość</th>
                                                <th scope="col">Cena</th>
                                                <th scope="col"><span class="sr-only">Koszyk</span></th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <template x-for="line in group.lines" :key="line.offerId">
                                                <tr>
                                                    <th scope="row">
                                                        <a :href="catalogUrl(line.area, line.id)" target="_blank" rel="noopener" x-text="line.name"></a>
                                                    </th>
                                                    <td x-text="line.variant !== '' ? line.variant : 'bez wariantu'"></td>
                                                    <td class="tabular-nums">
                                                        <span x-text="line.freeQty + ' szt.'"></span>
                                                        <span class="block text-xs font-semibold text-danger" x-show="line.isPartial" x-text="'dostępne ' + line.freeQty + ' z ' + line.wanted"></span>
                                                    </td>
                                                    <td class="tabular-nums">
                                                        <strong x-text="line.priceFormatted"></strong>
                                                        <template x-if="line.isPromo">
                                                            <span class="block text-xs">
                                                                <span class="inline-flex items-center rounded-full border border-aqua bg-aqua-50 px-2 py-0.5 font-semibold text-aqua-700" x-text="'Promocja do ' + line.promoEndsAt"></span>
                                                                <span class="block text-muted" x-text="line.lowestPriorLabel"></span>
                                                            </span>
                                                        </template>
                                                        <span class="block text-xs text-muted" x-text="'Ważna do ' + line.validTo"></span>
                                                    </td>
                                                    <td>
                                                        <template x-if="config.canCart">
                                                            <button
                                                                type="button"
                                                                class="creator-pill"
                                                                :disabled="cartPending"
                                                                :aria-label="'Do koszyka: ' + line.name + ', ' + line.takeQty + ' szt.'"
                                                                @click="addOffersToCart([{ offer_id: line.offerId, wanted: line.wanted }])"
                                                            >
                                                                <span x-text="'Do koszyka (' + line.takeQty + ')'"></span>
                                                            </button>
                                                        </template>
                                                    </td>
                                                </tr>
                                            </template>
                                        </tbody>
                                    </table>
                                </div>

                                <div class="flex flex-wrap items-center justify-between gap-2 pt-1">
                                    <template x-if="config.canCart">
                                        <button
                                            type="button"
                                            class="creator-add-btn"
                                            :disabled="cartPending"
                                            @click="addLocationToCart(group)"
                                        >
                                            <span x-text="cartPending ? 'Dodawanie do koszyka…' : 'Dodaj dostępne pozycje z tej placówki do koszyka'"></span>
                                        </button>
                                    </template>
                                    <template x-if="!config.isAuthenticated">
                                        <a :href="config.loginUrl" class="creator-pill">Zaloguj się, aby dodać do koszyka</a>
                                    </template>
                                </div>
                            </article>
                        </template>

                        <div class="creator-compat-box" x-show="(offersResult.missing ?? []).length > 0">
                            <h4 class="m-0 text-base font-bold text-ink">Pozycje bez dostępnych ofert</h4>
                            <p class="mt-1 mb-3 text-sm text-text-2">
                                <span x-show="offersResult.city">Dla tych pozycji brakuje obecnie aktywnych ofert w wybranym mieście.</span>
                                <span x-show="!offersResult.city">Dla tych pozycji brakuje obecnie aktywnych ofert w sklepach.</span>
                            </p>
                            <ul class="creator-compat-list">
                                <template x-for="item in (offersResult.missing ?? [])" :key="item.area + ':' + item.id">
                                    <li class="creator-compat-item flex flex-wrap items-center justify-between gap-3">
                                        <div>
                                            <p class="m-0 font-semibold text-ink">
                                                <a :href="catalogUrl(item.area, item.id)" target="_blank" rel="noopener" x-text="item.name"></a>
                                                <span class="text-sm font-normal text-muted tabular-nums" x-text="'(' + item.wanted + ' szt.)'"></span>
                                            </p>
                                            <p class="m-0 text-xs text-muted" x-text="item.areaLabel"></p>
                                            <p class="field-error" role="alert" x-show="watchErrorFor(item.area, item.id)" x-text="watchErrorFor(item.area, item.id)"></p>
                                        </div>

                                        <div>
                                            <template x-if="isWatched(item.area, item.id)">
                                                <div class="flex flex-wrap items-center gap-2 text-sm" role="status" aria-live="polite">
                                                    <span class="font-semibold text-aqua-700">Obserwujesz ten rekord.</span>
                                                    <a :href="config.watchesUrl" class="creator-pill">Moje obserwacje</a>
                                                </div>
                                            </template>
                                            <template x-if="!isWatched(item.area, item.id) && config.canWatch">
                                                <button
                                                    type="button"
                                                    class="creator-pill"
                                                    :disabled="isWatchPending(item.area, item.id)"
                                                    :aria-label="'Daj znać, gdy będzie dostępne: ' + item.name"
                                                    @click="watchMissingItem(item)"
                                                >
                                                    <x-heroicon-o-bell class="icon" aria-hidden="true" />
                                                    <span x-text="isWatchPending(item.area, item.id) ? 'Zapisywanie…' : 'Daj znać, gdy będzie dostępne'"></span>
                                                </button>
                                            </template>
                                            <template x-if="!isWatched(item.area, item.id) && !config.isAuthenticated">
                                                <a :href="config.registerUrl" class="creator-pill">
                                                    <x-heroicon-o-bell class="icon" aria-hidden="true" />
                                                    Załóż konto, by otrzymać powiadomienie
                                                </a>
                                            </template>
                                        </div>
                                    </li>
                                </template>
                            </ul>
                        </div>
                    </div>
                </template>
            </div>
        </template>
    </div>

    {{-- Zapis planu na koncie i konwersja w zbiornik --}}
    <div class="creator-section-block" aria-labelledby="creator-save-heading">
        <h3 id="creator-save-heading" class="creator-section-title">Zapis planu i utworzenie zbiornika</h3>

        <div class="creator-notice" role="status" aria-live="polite" x-show="actionPromptNote" x-cloak>
            <p class="m-0 text-sm font-semibold" x-text="actionPromptNote"></p>
        </div>

        <template x-if="!config.isAuthenticated">
            <div class="creator-compat-box">
                <p class="m-0 font-semibold text-ink">Zapisz plan na swoim koncie Aikwarium</p>
                <p class="mt-2 mb-0 text-sm text-text-2">
                    Po zalogowaniu przeniesiemy Twój obecny szkic z przeglądarki na konto, aby zapisać go jako plan i utworzyć z niego zbiornik.
                </p>
                <div class="mt-4 flex flex-wrap gap-2">
                    <a :href="config.loginUrl" class="creator-add-btn no-underline">Zaloguj się, aby zapisać plan</a>
                    <a :href="config.registerUrl" class="creator-pill no-underline">Załóż bezpłatne konto</a>
                </div>
            </div>
        </template>

        <template x-if="config.isAuthenticated && config.needsConsents">
            <div class="creator-compat-box">
                <p class="m-0 font-semibold text-ink">Wymagana akceptacja aktualnych dokumentów</p>
                <p class="mt-2 mb-0 text-sm text-text-2">
                    Przed zapisaniem planu na koncie potwierdź aktualny regulamin i politykę prywatności. Szkic w przeglądarce zostanie zachowany.
                </p>
                <div class="mt-4">
                    <a :href="config.consentsUrl" class="creator-add-btn">Przejdź do zgód i wróć do zapisu</a>
                </div>
            </div>
        </template>

        <template x-if="config.isAuthenticated && !config.needsConsents">
            <div class="space-y-4">
                <div class="creator-notice creator-notice-warn" role="alert" x-show="saveError" x-cloak>
                    <p class="m-0 text-sm font-semibold" x-text="saveError"></p>
                    <div class="mt-3 flex flex-wrap gap-2" x-show="saveCode === 'plan_limit'">
                        <template x-for="draft in draftPlans" :key="draft.id">
                            <button
                                type="button"
                                class="creator-add-btn"
                                :disabled="planSaving"
                                @click="savePlan(draft.id)"
                            >
                                <span x-text="'Zastąp obecny szkic «' + draft.name + '»'"></span>
                            </button>
                        </template>
                        <a :href="config.plansUrl" class="creator-pill">Przejdź do listy planów</a>
                    </div>
                </div>

                <div class="creator-notice" role="status" aria-live="polite" x-show="savedPlan" x-cloak>
                    <p class="m-0 text-sm font-semibold text-ink" x-text="savedPlan?.message"></p>
                    <div class="mt-3 flex flex-wrap items-center gap-2">
                        <a :href="savedPlan?.planUrl" class="creator-pill">Otwórz zapisany plan</a>
                        <button
                            type="button"
                            class="creator-add-btn"
                            :disabled="converting || Boolean(convertedTank)"
                            @click="convertSavedPlan()"
                        >
                            <span x-text="converting ? 'Tworzenie zbiornika…' : 'Utwórz akwarium z planu'"></span>
                        </button>
                    </div>
                </div>

                <div class="creator-notice creator-notice-warn" role="alert" x-show="convertError" x-cloak>
                    <p class="m-0 text-sm font-semibold" x-text="convertError"></p>
                </div>

                <div class="creator-notice" role="status" aria-live="polite" x-show="convertedTank" x-cloak>
                    <p class="m-0 text-sm font-semibold text-ink" x-text="convertedTank?.message"></p>
                    <div class="mt-3">
                        <a :href="convertedTank?.tankUrl" class="creator-add-btn">Przejdź do zbiornika</a>
                    </div>
                </div>

                <div class="flex flex-wrap items-center gap-3" x-show="config.canPlan">
                    <button
                        type="button"
                        class="creator-add-btn"
                        :disabled="planSaving"
                        @click="savePlan()"
                    >
                        <span x-text="planSaving ? 'Zapisywanie planu…' : 'Zapisz plan na koncie'"></span>
                    </button>
                    <a :href="config.plansUrl" class="creator-pill">Moje plany</a>
                </div>
            </div>
        </template>
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

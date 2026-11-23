<section
    id="creator-step-1"
    class="creator-step-panel"
    x-show="step === 1"
    aria-labelledby="creator-heading-1"
>
    <p class="eyebrow">Krok 1 z 6</p>
    <h2 id="creator-heading-1" tabindex="-1" x-ref="stepHeading1">Zbiornik i parametry wody</h2>
    <p class="creator-step-lead">Wybierz pasmo wielkości albo podaj dokładny litraż lub wymiary szkła. Litraż jest jawnie zaokrąglany przed wyznaczeniem pasma.</p>

    <div class="creator-section-block">
        <div class="creator-field max-w-md">
            <label for="creator-plan-name">Nazwa robocza planu (opcjonalnie)</label>
            <input
                id="creator-plan-name"
                type="text"
                maxlength="100"
                placeholder="np. Salon 112 l lub Krewetkarium"
                x-model="planName"
                @input="scheduleSave()"
            >
        </div>
    </div>

    <div class="creator-section-block">
        <h3 class="creator-section-title">Sposób określenia wielkości</h3>
        <div class="creator-pill-group" role="group" aria-label="Sposób określenia wielkości zbiornika">
            <button type="button" class="creator-pill" :aria-pressed="tank.mode === 'band'" @click="setMode('band')">Pasmo wielkości</button>
            <button type="button" class="creator-pill" :aria-pressed="tank.mode === 'liters'" @click="setMode('liters')">Dokładny litraż</button>
            <button type="button" class="creator-pill" :aria-pressed="tank.mode === 'dimensions'" @click="setMode('dimensions')">Wymiary akwarium</button>
        </div>

        <div class="mt-4" x-show="tank.mode === 'band'">
            <div class="creator-choice-grid" role="group" aria-label="Pasmo wielkości zbiornika">
                <template x-for="band in (bundle?.dicts?.tankBands ?? [])" :key="band.code">
                    <button
                        type="button"
                        class="creator-choice-card"
                        :aria-pressed="tank.sizeBand === band.code"
                        @click="selectBand(band.code)"
                    >
                        <span class="creator-choice-title">
                            <span x-text="band.label"></span>
                            <x-heroicon-o-check-circle class="icon text-aqua" x-show="tank.sizeBand === band.code" aria-hidden="true" />
                        </span>
                        <span class="creator-choice-meta tabular-nums" x-text="'Orientacyjnie ok. ' + band.volume + ' l'"></span>
                    </button>
                </template>
            </div>
        </div>

        <div class="mt-4 space-y-4" x-show="tank.mode === 'liters'" x-cloak>
            <div class="creator-field-grid">
                <div class="creator-field">
                    <label for="creator-liters">Litraż brutto (l)</label>
                    <input
                        id="creator-liters"
                        type="text"
                        inputmode="decimal"
                        placeholder="np. 112"
                        x-model="tank.liters"
                        :aria-invalid="Boolean(volumeResult.errors?.liters)"
                        @input="onTankChange()"
                    >
                    <p class="field-error" role="alert" x-show="volumeResult.errors?.liters" x-text="volumeResult.errors?.liters"></p>
                </div>
                <div class="creator-field">
                    <label for="creator-liters-deduct">Litry do odjęcia, np. podłoże (opcjonalnie)</label>
                    <input
                        id="creator-liters-deduct"
                        type="text"
                        inputmode="decimal"
                        placeholder="np. 10"
                        x-model="tank.deduct"
                        :aria-invalid="Boolean(volumeResult.errors?.deduct)"
                        @input="onTankChange()"
                    >
                    <p class="field-error" role="alert" x-show="volumeResult.errors?.deduct" x-text="volumeResult.errors?.deduct"></p>
                </div>
            </div>
        </div>

        <div class="mt-4 space-y-4" x-show="tank.mode === 'dimensions'" x-cloak>
            <div class="creator-pill-group" role="group" aria-label="Kształt zbiornika">
                <button type="button" class="creator-pill" :aria-pressed="tank.shape === 'cuboid'" @click="tank.shape = 'cuboid'; onTankChange()">Prostopadłościan</button>
                <button type="button" class="creator-pill" :aria-pressed="tank.shape === 'cylinder'" @click="tank.shape = 'cylinder'; onTankChange()">Walec</button>
            </div>

            <div class="creator-field-grid">
                <template x-if="tank.shape === 'cuboid'">
                    <div class="creator-field">
                        <label for="creator-length">Długość (cm)</label>
                        <input
                            id="creator-length"
                            type="text"
                            inputmode="decimal"
                            placeholder="np. 80"
                            x-model="tank.length"
                            :aria-invalid="Boolean(volumeResult.errors?.length)"
                            @input="onTankChange()"
                        >
                        <p class="field-error" role="alert" x-show="volumeResult.errors?.length" x-text="volumeResult.errors?.length"></p>
                    </div>
                </template>
                <template x-if="tank.shape === 'cuboid'">
                    <div class="creator-field">
                        <label for="creator-width">Szerokość (cm)</label>
                        <input
                            id="creator-width"
                            type="text"
                            inputmode="decimal"
                            placeholder="np. 35"
                            x-model="tank.width"
                            :aria-invalid="Boolean(volumeResult.errors?.width)"
                            @input="onTankChange()"
                        >
                        <p class="field-error" role="alert" x-show="volumeResult.errors?.width" x-text="volumeResult.errors?.width"></p>
                    </div>
                </template>
                <template x-if="tank.shape === 'cylinder'">
                    <div class="creator-field">
                        <label for="creator-diameter">Średnica (cm)</label>
                        <input
                            id="creator-diameter"
                            type="text"
                            inputmode="decimal"
                            placeholder="np. 40"
                            x-model="tank.diameter"
                            :aria-invalid="Boolean(volumeResult.errors?.diameter)"
                            @input="onTankChange()"
                        >
                        <p class="field-error" role="alert" x-show="volumeResult.errors?.diameter" x-text="volumeResult.errors?.diameter"></p>
                    </div>
                </template>
                <div class="creator-field">
                    <label for="creator-height">Wysokość lustra wody (cm)</label>
                    <input
                        id="creator-height"
                        type="text"
                        inputmode="decimal"
                        placeholder="np. 40"
                        x-model="tank.height"
                        :aria-invalid="Boolean(volumeResult.errors?.height)"
                        @input="onTankChange()"
                    >
                    <p class="field-error" role="alert" x-show="volumeResult.errors?.height" x-text="volumeResult.errors?.height"></p>
                </div>
                <div class="creator-field">
                    <label for="creator-dim-deduct">Litry do odjęcia (opcjonalnie)</label>
                    <input
                        id="creator-dim-deduct"
                        type="text"
                        inputmode="decimal"
                        placeholder="np. 12"
                        x-model="tank.deduct"
                        :aria-invalid="Boolean(volumeResult.errors?.deduct)"
                        @input="onTankChange()"
                    >
                    <p class="field-error" role="alert" x-show="volumeResult.errors?.deduct" x-text="volumeResult.errors?.deduct"></p>
                </div>
            </div>
        </div>

        <div class="creator-notice mt-4" x-show="tank.mode !== 'band' && volumeResult.valid" x-cloak>
            <p class="font-semibold text-ink tabular-nums">
                Wyznaczone pasmo: <span x-text="activeTankBand?.label ?? ''"></span>
                (<span x-text="volumeResult.savedLiters"></span> l po zaokrągleniu; próg pasma: <span x-text="volumeResult.bandLiters"></span> l)
            </p>
            <ul class="m-0 list-disc pl-5 text-sm tabular-nums" x-show="(volumeResult.formulaSteps ?? []).length > 0">
                <template x-for="(formulaStep, idx) in (volumeResult.formulaSteps ?? [])" :key="idx">
                    <li x-text="formulaStep"></li>
                </template>
            </ul>
        </div>
    </div>

    <div class="creator-section-block">
        <h3 class="creator-section-title">Typ akwarium</h3>
        <div class="creator-choice-grid" role="group" aria-label="Typ akwarium">
            <template x-for="typeLabel in (bundle?.dicts?.tankTypes ?? [])" :key="typeLabel">
                <button
                    type="button"
                    class="creator-choice-card"
                    :aria-pressed="tank.type === typeLabel"
                    @click="selectType(typeLabel)"
                >
                    <span class="creator-choice-title">
                        <span x-text="typeLabel"></span>
                        <x-heroicon-o-check-circle class="icon text-aqua" x-show="tank.type === typeLabel" aria-hidden="true" />
                    </span>
                </button>
            </template>
        </div>
    </div>

    <div class="creator-section-block">
        <h3 class="creator-section-title">Opcjonalne pasma wody</h3>
        <p class="text-sm text-muted">Jeśli znasz planowaną temperaturę lub pH wody, zawężą ranking i porównania katalogowe.</p>

        <div class="mt-3 space-y-4">
            <div>
                <p class="creator-filter-label mb-2">Temperatura</p>
                <div class="creator-pill-group" role="group" aria-label="Pasmo temperatury wody">
                    <button type="button" class="creator-pill" :aria-pressed="tank.tempBand === null" @click="tank.tempBand = null; onTankChange()">Nieokreślona</button>
                    <template x-for="tb in (bundle?.dicts?.tempBands ?? [])" :key="tb.code">
                        <button
                            type="button"
                            class="creator-pill"
                            :aria-pressed="tank.tempBand === tb.code"
                            @click="toggleTempBand(tb.code)"
                        >
                            <span x-text="tb.rangeLabel"></span>
                        </button>
                    </template>
                </div>
            </div>

            <div>
                <p class="creator-filter-label mb-2">Odczyn pH</p>
                <div class="creator-pill-group" role="group" aria-label="Pasmo pH wody">
                    <button type="button" class="creator-pill" :aria-pressed="tank.phBand === null" @click="tank.phBand = null; onTankChange()">Nieokreślone</button>
                    <template x-for="pb in (bundle?.dicts?.phBands ?? [])" :key="pb.code">
                        <button
                            type="button"
                            class="creator-pill"
                            :aria-pressed="tank.phBand === pb.code"
                            @click="togglePhBand(pb.code)"
                        >
                            <span x-text="pb.label"></span>
                        </button>
                    </template>
                </div>
            </div>
        </div>
    </div>

    <div class="creator-step-footer">
        <span class="text-sm text-muted">Możesz przejść dalej lub przeskoczyć dowolny krok na osi powyżej.</span>
        <button type="button" class="creator-add-btn" @click="goToStep(2)">
            Dalej: Ryby i bezkręgowce <x-heroicon-o-arrow-right class="icon" aria-hidden="true" />
        </button>
    </div>
</section>

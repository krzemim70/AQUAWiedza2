import {
    calculateVolume,
    checkCompatibility,
    filterFlora,
    rankFauna,
    recommendCare,
    recommendEquipment,
} from './creator/engine.js';

const STORAGE_KEY = 'aikwarium.creator.v1';
const SAVE_DEBOUNCE_MS = 250;

const POLISH_FOLD_MAP = {
    ą: 'a', ć: 'c', ę: 'e', ł: 'l', ń: 'n', ó: 'o', ś: 's', ź: 'z', ż: 'z',
    Ą: 'a', Ć: 'c', Ę: 'e', Ł: 'l', Ń: 'n', Ó: 'o', Ś: 's', Ź: 'z', Ż: 'z',
    ø: 'o', Ø: 'o',
};

const AREA_LABELS = {
    fauna: 'Fauna',
    flora: 'Flora',
    equipment: 'Sprzęt',
    care: 'Pielęgnacja',
};

const CHECK_LABELS = {
    tank_too_small: 'Pasmo wielkości zbiornika',
    tank_type: 'Typ zbiornika',
    temperature_pair: 'Temperatura (pary gatunków)',
    temperature_tank: 'Temperatura wobec ustawień zbiornika',
    ph_pair: 'pH (pary gatunków)',
    ph_tank: 'pH wobec ustawień zbiornika',
    temperament: 'Etykiety temperamentu',
    co2_missing: 'CO₂ roślin a sprzęt zbiornika',
};

const CHECK_UNITS = {
    temperature_pair: 'par',
    ph_pair: 'par',
};

const BAND_BOX_SIZES = {
    nano: { w: 148, h: 96 },
    small: { w: 184, h: 106 },
    medium: { w: 220, h: 118 },
    large: { w: 252, h: 128 },
    huge: { w: 278, h: 136 },
};

function foldText(text) {
    return String(text ?? '')
        .replace(/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻøØ]/g, (ch) => POLISH_FOLD_MAP[ch] ?? ch)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function toNullableNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : null;
    }
    const normalized = String(value).trim().replace(',', '.');
    if (normalized === '') {
        return null;
    }
    const num = Number(normalized);

    return Number.isFinite(num) ? num : null;
}

function clamp(value, min, max) {
    return Math.min(max, Math.max(min, value));
}

function defaultTank() {
    return {
        mode: 'band',
        sizeBand: 'medium',
        type: 'Ogólne / Towarzyskie',
        liters: null,
        shape: 'cuboid',
        length: null,
        width: null,
        height: null,
        diameter: null,
        deduct: null,
        tempBand: null,
        phBand: null,
    };
}

function sanitizeDraft(raw) {
    if (!raw || typeof raw !== 'object' || raw.schema !== 1) {
        return null;
    }

    const baseTank = defaultTank();
    const rawTank = raw.tank && typeof raw.tank === 'object' ? raw.tank : {};
    const mode = ['band', 'liters', 'dimensions'].includes(rawTank.mode) ? rawTank.mode : baseTank.mode;
    const shape = ['cuboid', 'cylinder'].includes(rawTank.shape) ? rawTank.shape : baseTank.shape;

    const tank = {
        mode,
        sizeBand: typeof rawTank.sizeBand === 'string' && rawTank.sizeBand !== '' ? rawTank.sizeBand : baseTank.sizeBand,
        type: typeof rawTank.type === 'string' && rawTank.type !== '' ? rawTank.type : baseTank.type,
        liters: toNullableNumber(rawTank.liters),
        shape,
        length: toNullableNumber(rawTank.length),
        width: toNullableNumber(rawTank.width),
        height: toNullableNumber(rawTank.height),
        diameter: toNullableNumber(rawTank.diameter),
        deduct: toNullableNumber(rawTank.deduct),
        tempBand: typeof rawTank.tempBand === 'string' && rawTank.tempBand !== '' ? rawTank.tempBand : null,
        phBand: typeof rawTank.phBand === 'string' && rawTank.phBand !== '' ? rawTank.phBand : null,
    };

    const validAreas = new Set(['fauna', 'flora', 'equipment', 'care']);
    const seenKeys = new Set();
    const items = [];
    if (Array.isArray(raw.items)) {
        for (const entry of raw.items) {
            if (!entry || typeof entry !== 'object') {
                continue;
            }
            const area = validAreas.has(entry.area) ? entry.area : null;
            const id = typeof entry.id === 'string' ? entry.id.trim() : '';
            const qty = Math.floor(Number(entry.qty) || 0);
            if (!area || id === '' || qty <= 0) {
                continue;
            }
            const key = `${area}:${id}`;
            if (seenKeys.has(key)) {
                continue;
            }
            seenKeys.add(key);
            items.push({ area, id, qty });
        }
    }

    const rawStep = Math.floor(Number(raw.step) || 1);

    return {
        batchId: typeof raw.batchId === 'number' && Number.isFinite(raw.batchId) ? raw.batchId : null,
        step: clamp(rawStep, 1, 6),
        planName: typeof raw.planName === 'string' ? raw.planName : '',
        tank,
        items,
    };
}

function readStoredDraft() {
    try {
        const raw = window.localStorage.getItem(STORAGE_KEY);
        if (!raw) {
            return null;
        }

        return sanitizeDraft(JSON.parse(raw));
    } catch {
        return null;
    }
}

function writeStoredDraft(payload) {
    try {
        window.localStorage.setItem(STORAGE_KEY, JSON.stringify(payload));
    } catch {
        // Pamięć lokalna może być niedostępna w trybie prywatnym.
    }
}

function removeStoredDraft() {
    try {
        window.localStorage.removeItem(STORAGE_KEY);
    } catch {
        // Ignorujemy brak dostępu do localStorage.
    }
}

function aquariumCreator(config = {}) {
    return {
        config,
        bundle: null,
        loading: Boolean(config.hasCatalog),
        loadError: false,
        step: 1,
        planName: '',
        tank: defaultTank(),
        items: [],
        loadedDraftBatchId: null,
        draftStatusNote: null,
        clearConfirmOpen: false,
        saveTimer: null,

        steps: [
            { id: 1, label: 'Zbiornik', area: null },
            { id: 2, label: 'Ryby i bezkręgowce', area: 'fauna' },
            { id: 3, label: 'Rośliny', area: 'flora' },
            { id: 4, label: 'Sprzęt', area: 'equipment' },
            { id: 5, label: 'Pielęgnacja', area: 'care' },
            { id: 6, label: 'Lista zakupów', area: null },
        ],

        faunaFilters: {
            query: '',
            zone: null,
            kind: null,
            compatOnly: false,
        },
        floraFilters: {
            query: '',
            plantZone: '',
            difficulty: '',
            co2: '',
        },
        equipmentFilters: {
            query: '',
            category: 'Filtracja',
        },
        careFilters: {
            query: '',
            group: 'start',
        },

        recordsByKey: {},
        volumeResult: { valid: true, errors: {}, grossLiters: 175, netLiters: null, savedLiters: 175, bandLiters: 175, sizeBand: 'medium', formulaSteps: [] },
        faunaRanking: { ranked: [], topCount: 1, decidedBy: null, excluded: 0, noData: 0, effectiveFishBand: null, inferredFish: true },
        compatibility: { assessed: false, verdict: 'clear', findings: [], rows: [], unknown: 0, discrepancies: [] },
        faunaCandidateNotes: {},
        equipmentResult: { categories: [], hints: { heating: { status: 'none', text: null, evidence: [] }, co2: { status: 'none', text: null, evidence: [] } }, missingSpecsNote: 'Moc i przepływ: brak danych w bazie — sprawdź na opakowaniu.' },
        careResult: { start: [], feeding: { items: [], domains: [], snailFoodMissing: false, snailFoodNote: null, notes: [] }, plants: [], testing: [], snailFoodMissing: false, snailFoodNote: null },
        floraZones: [],
        floraDifficulties: [],

        async init() {
            const draft = readStoredDraft();
            if (draft) {
                this.step = draft.step;
                this.planName = draft.planName;
                this.tank = draft.tank;
                this.items = draft.items;
                this.loadedDraftBatchId = draft.batchId;
            }

            if (!this.config.hasCatalog || !this.config.bundleUrl) {
                this.loading = false;

                return;
            }

            await this.loadBundle();
        },

        async loadBundle() {
            this.loading = true;
            this.loadError = false;

            try {
                const response = await fetch(this.config.bundleUrl, {
                    credentials: 'same-origin',
                    headers: { Accept: 'application/json' },
                });
                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}`);
                }
                const data = await response.json();
                this.bundle = data;

                const index = {};
                for (const area of ['fauna', 'flora', 'equipment', 'care']) {
                    for (const rec of data[area] ?? []) {
                        index[`${area}:${rec.id}`] = rec;
                    }
                }
                this.recordsByKey = index;

                const floraZonesSet = new Set();
                const floraDiffSet = new Set();
                for (const rec of data.flora ?? []) {
                    if (rec.plantZone) {
                        floraZonesSet.add(rec.plantZone);
                    }
                    if (rec.difficulty) {
                        floraDiffSet.add(rec.difficulty);
                    }
                }
                this.floraZones = [...floraZonesSet];
                this.floraDifficulties = [...floraDiffSet];

                if (data.rules?.equipmentCategories?.length && !data.rules.equipmentCategories.includes(this.equipmentFilters.category)) {
                    this.equipmentFilters.category = data.rules.equipmentCategories[0];
                }

                const types = data.dicts?.tankTypes ?? [];
                if (types.length > 0 && !types.includes(this.tank.type)) {
                    this.tank.type = types.includes('Ogólne / Towarzyskie') ? 'Ogólne / Towarzyskie' : types[0];
                }

                this.recompute();

                const unknownCount = this.unknownItemsCount;
                if (this.loadedDraftBatchId !== null && data.batchId !== null && this.loadedDraftBatchId !== data.batchId) {
                    if (unknownCount > 0) {
                        this.draftStatusNote = `Katalog został zaktualizowany. Liczba pozycji ze szkicu spoza aktualnego katalogu: ${unknownCount}. Zestaw został przeliczony na nowym pakiecie.`;
                    }
                    this.scheduleSave();
                } else if (unknownCount > 0) {
                    this.draftStatusNote = `W szkicu znajduje się ${unknownCount} poz. spoza aktualnego katalogu.`;
                }
            } catch {
                this.loadError = true;
            } finally {
                this.loading = false;
            }
        },

        onTankChange() {
            this.recompute();
            this.scheduleSave();
        },

        recompute() {
            if (!this.bundle) {
                return;
            }

            const vol = calculateVolume(
                {
                    mode: this.tank.mode,
                    sizeBand: this.tank.sizeBand,
                    liters: this.tank.liters,
                    shape: this.tank.shape,
                    length: this.tank.length,
                    width: this.tank.width,
                    height: this.tank.height,
                    diameter: this.tank.diameter,
                    deduct: this.tank.deduct,
                },
                this.bundle.rules,
            );
            this.volumeResult = vol;

            const activeSizeBand = this.tank.mode === 'band'
                ? this.tank.sizeBand
                : (vol.valid ? vol.sizeBand : null);

            if (this.tank.mode !== 'band' && vol.valid && vol.sizeBand) {
                this.tank.sizeBand = vol.sizeBand;
            }

            const tankContext = {
                sizeBand: activeSizeBand,
                type: this.tank.type,
                tempBand: this.tank.tempBand,
                phBand: this.tank.phBand,
            };

            this.faunaRanking = rankFauna(this.bundle, tankContext);
            this.compatibility = checkCompatibility(this.bundle, tankContext, this.items);
            this.equipmentResult = recommendEquipment(this.bundle, tankContext, this.items);
            this.careResult = recommendCare(this.bundle, this.items);

            const notes = {};
            const hasStockOrWater = this.selectedCountForArea('fauna') > 0
                || this.selectedCountForArea('flora') > 0
                || Boolean(this.tank.tempBand)
                || Boolean(this.tank.phBand)
                || Boolean(activeSizeBand);

            if (hasStockOrWater) {
                for (const rec of this.bundle.fauna ?? []) {
                    const check = checkCompatibility(this.bundle, tankContext, this.items, { area: 'fauna', id: rec.id });
                    const relevantFindings = check.findings.filter((f) => {
                        if (f.check === 'temperament') {
                            return false;
                        }
                        if (f.check === 'tank_too_small' && f.severity === 'info') {
                            return false;
                        }

                        return f.severity === 'warning' || f.severity === 'conflict';
                    });
                    const discrepancies = check.discrepancies ?? [];
                    notes[rec.id] = {
                        hasWarning: relevantFindings.length > 0 || discrepancies.length > 0,
                        findings: relevantFindings,
                        discrepancies,
                    };
                }
            }
            this.faunaCandidateNotes = notes;
        },

        scheduleSave() {
            if (this.saveTimer) {
                window.clearTimeout(this.saveTimer);
            }
            this.saveTimer = window.setTimeout(() => {
                this.persistNow();
            }, SAVE_DEBOUNCE_MS);
        },

        persistNow() {
            writeStoredDraft({
                schema: 1,
                batchId: this.bundle?.batchId ?? this.loadedDraftBatchId ?? null,
                step: this.step,
                planName: this.planName,
                tank: {
                    mode: this.tank.mode,
                    sizeBand: this.tank.sizeBand,
                    type: this.tank.type,
                    liters: toNullableNumber(this.tank.liters),
                    shape: this.tank.shape,
                    length: toNullableNumber(this.tank.length),
                    width: toNullableNumber(this.tank.width),
                    height: toNullableNumber(this.tank.height),
                    diameter: toNullableNumber(this.tank.diameter),
                    deduct: toNullableNumber(this.tank.deduct),
                    tempBand: this.tank.tempBand,
                    phBand: this.tank.phBand,
                },
                items: this.items.map((item) => ({
                    area: item.area,
                    id: item.id,
                    qty: item.qty,
                })),
                updatedAt: new Date().toISOString(),
            });
        },

        clearDraft() {
            if (this.saveTimer) {
                window.clearTimeout(this.saveTimer);
                this.saveTimer = null;
            }
            removeStoredDraft();
            this.planName = '';
            this.tank = defaultTank();
            this.items = [];
            this.loadedDraftBatchId = null;
            this.draftStatusNote = null;
            this.clearConfirmOpen = false;
            this.faunaFilters = { query: '', zone: null, kind: null, compatOnly: false };
            this.floraFilters = { query: '', plantZone: '', difficulty: '', co2: '' };
            this.equipmentFilters = {
                query: '',
                category: this.bundle?.rules?.equipmentCategories?.[0] ?? 'Filtracja',
            };
            this.careFilters = { query: '', group: 'start' };
            this.recompute();
            this.goToStep(1);
        },

        removeWithdrawnItems() {
            if (!this.bundle) {
                return;
            }
            this.items = this.items.filter((item) => Boolean(this.recordsByKey[`${item.area}:${item.id}`]));
            this.draftStatusNote = null;
            this.recompute();
            this.scheduleSave();
        },

        goToStep(nextStep) {
            const target = clamp(Math.floor(Number(nextStep) || 1), 1, 6);
            if (target === this.step) {
                this.focusStepHeading(target);

                return;
            }

            const apply = () => {
                this.step = target;
                this.scheduleSave();
            };

            const reduceMotion = !window.matchMedia('(prefers-reduced-motion: no-preference)').matches;
            if (typeof document.startViewTransition === 'function' && !reduceMotion) {
                try {
                    const transition = document.startViewTransition(() => {
                        apply();

                        return this.$nextTick();
                    });
                    transition.finished.finally(() => this.focusStepHeading(target));

                    return;
                } catch {
                    // Fallback synchroniczny, jeśli przeglądarka przerwie przejście.
                }
            }

            apply();
            this.$nextTick(() => this.focusStepHeading(target));
        },

        focusStepHeading(stepNumber) {
            const heading = this.$refs[`stepHeading${stepNumber}`];
            if (heading && typeof heading.focus === 'function') {
                heading.focus({ preventScroll: false });
            }
        },

        setMode(mode) {
            this.tank.mode = mode;
            this.onTankChange();
        },

        selectBand(code) {
            this.tank.mode = 'band';
            this.tank.sizeBand = code;
            this.onTankChange();
        },

        selectType(type) {
            this.tank.type = type;
            this.onTankChange();
        },

        toggleTempBand(code) {
            this.tank.tempBand = this.tank.tempBand === code ? null : code;
            this.onTankChange();
        },

        togglePhBand(code) {
            this.tank.phBand = this.tank.phBand === code ? null : code;
            this.onTankChange();
        },

        qtyOf(area, id) {
            const found = this.items.find((i) => i.area === area && i.id === id);

            return found ? found.qty : 0;
        },

        setQty(area, id, qty) {
            const nextQty = Math.max(0, Math.min(9999, Math.floor(Number(qty) || 0)));
            const idx = this.items.findIndex((i) => i.area === area && i.id === id);
            if (nextQty === 0) {
                if (idx !== -1) {
                    this.items.splice(idx, 1);
                }
            } else if (idx !== -1) {
                this.items[idx].qty = nextQty;
            } else {
                this.items.push({ area, id, qty: nextQty });
            }
            this.recompute();
            this.scheduleSave();
        },

        stepQty(area, id, delta) {
            this.setQty(area, id, this.qtyOf(area, id) + delta);
        },

        selectedCountForArea(area) {
            return this.items.filter((i) => i.area === area).length;
        },

        selectedTotalUnitsForArea(area) {
            return this.items
                .filter((i) => i.area === area)
                .reduce((sum, i) => sum + i.qty, 0);
        },

        get totalSelectedSpecies() {
            return this.items.length;
        },

        get totalSelectedUnits() {
            return this.items.reduce((sum, i) => sum + i.qty, 0);
        },

        get searchMinChars() {
            return this.bundle?.rules?.searchMinChars ?? 3;
        },

        isSearchTooShort(query) {
            const folded = foldText(query);

            return folded.length > 0 && folded.length < this.searchMinChars;
        },

        effectiveQuery(query) {
            const folded = foldText(query);

            return folded.length >= this.searchMinChars ? folded : '';
        },

        get activeTankBand() {
            const code = this.tank.mode === 'band'
                ? this.tank.sizeBand
                : (this.volumeResult.valid ? this.volumeResult.sizeBand : null);
            if (!code || !this.bundle) {
                return null;
            }

            return (this.bundle.dicts?.tankBands ?? []).find((b) => b.code === code) ?? null;
        },

        get displayVolumeText() {
            if (this.tank.mode === 'band') {
                const band = this.activeTankBand;

                return band ? `ok. ${band.volume} l (${band.label})` : 'Wybierz pasmo';
            }
            if (!this.volumeResult.valid) {
                return 'Uzupełnij poprawne dane';
            }
            const band = this.activeTankBand;
            const liters = this.volumeResult.savedLiters;

            return band ? `${liters} l · ${band.label}` : `${liters} l`;
        },

        get filteredFauna() {
            if (!this.bundle) {
                return [];
            }

            const q = this.effectiveQuery(this.faunaFilters.query);
            const zoneFilter = this.faunaFilters.zone;
            const kindFilter = this.faunaFilters.kind;
            const compatOnly = this.faunaFilters.compatOnly;

            return this.faunaRanking.ranked
                .map((rankedItem) => {
                    const record = this.recordsByKey[`fauna:${rankedItem.id}`];
                    if (!record) {
                        return null;
                    }

                    return { ...record, percent: rankedItem.percent };
                })
                .filter((record) => {
                    if (!record) {
                        return false;
                    }
                    if (zoneFilter !== null && record.zone !== zoneFilter) {
                        return false;
                    }
                    if (kindFilter !== null && record.kind !== kindFilter) {
                        return false;
                    }
                    if (compatOnly && this.faunaCandidateNotes[record.id]?.hasWarning) {
                        return false;
                    }
                    if (q !== '' && !(record.search ?? '').includes(q)) {
                        return false;
                    }

                    return true;
                });
        },

        get filteredFlora() {
            if (!this.bundle) {
                return [];
            }

            return filterFlora(
                this.bundle,
                {
                    sizeBand: this.activeTankBand?.code ?? null,
                    type: this.tank.type,
                },
                {
                    plantZone: this.floraFilters.plantZone || null,
                    difficulty: this.floraFilters.difficulty || null,
                    co2: this.floraFilters.co2 || null,
                    query: this.effectiveQuery(this.floraFilters.query),
                },
            );
        },

        get activeEquipmentCategory() {
            const categories = this.equipmentResult.categories ?? [];
            const selected = categories.find((c) => c.category === this.equipmentFilters.category);

            return selected ?? categories[0] ?? null;
        },

        get filteredEquipmentItems() {
            const cat = this.activeEquipmentCategory;
            if (!cat) {
                return [];
            }
            const q = this.effectiveQuery(this.equipmentFilters.query);
            if (q === '') {
                return cat.items;
            }

            return cat.items.filter((item) => (item.search ?? '').includes(q));
        },

        get filteredCareItems() {
            if (!this.bundle) {
                return [];
            }

            let list = [];
            const group = this.careFilters.group;
            if (group === 'start') {
                list = this.careResult.start ?? [];
            } else if (group === 'feeding') {
                list = this.careResult.feeding?.items ?? [];
            } else if (group === 'plants') {
                list = this.careResult.plants ?? [];
            } else if (group === 'testing') {
                list = this.careResult.testing ?? [];
            } else {
                list = this.bundle.care ?? [];
            }

            const q = this.effectiveQuery(this.careFilters.query);
            if (q === '') {
                return list;
            }

            return list.filter((item) => (item.search ?? '').includes(q));
        },

        get withdrawnItems() {
            if (!this.bundle) {
                return [];
            }

            return this.items.filter((item) => !this.recordsByKey[`${item.area}:${item.id}`]);
        },

        get unknownItemsCount() {
            return Math.max(this.compatibility.unknown ?? 0, this.withdrawnItems.length);
        },

        get groupedSelectedItems() {
            return ['fauna', 'flora', 'equipment', 'care'].map((area) => ({
                area,
                label: AREA_LABELS[area],
                items: this.items
                    .filter((item) => item.area === area)
                    .map((item) => {
                        const rec = this.recordsByKey[`${area}:${item.id}`] ?? null;

                        return {
                            area,
                            id: item.id,
                            qty: item.qty,
                            known: rec !== null,
                            name: rec?.name ?? item.id,
                            latin: rec?.latin ?? null,
                            meta: rec ? this.itemMetaText(area, rec) : 'Pozycja spoza aktualnego katalogu',
                            thumb: rec?.thumb ?? null,
                        };
                    }),
            })).filter((group) => group.items.length > 0);
        },

        get flatSelectedItems() {
            return this.groupedSelectedItems.flatMap((group) => group.items.map((item) => ({
                ...item,
                groupLabel: group.label,
            })));
        },

        get compatibilityVerdictLabel() {
            if (this.selectedCountForArea('fauna') === 0 && this.selectedCountForArea('flora') === 0) {
                return 'Dodaj gatunki, aby zobaczyć porównanie';
            }

            return this.compatibility.verdict === 'clear'
                ? 'Porównanie wpisów katalogu'
                : 'Różnice w danych katalogowych';
        },

        get visibleFindings() {
            return (this.compatibility.findings ?? []).filter((f) => f.check !== 'temperament');
        },

        get temperamentFindingsCount() {
            return (this.compatibility.findings ?? []).filter((f) => f.check === 'temperament').length;
        },

        get svgLayout() {
            const viewW = 320;
            const viewH = 200;
            const isCylinder = this.tank.mode === 'dimensions' && this.tank.shape === 'cylinder';

            let boxW = 220;
            let boxH = 118;

            if (this.tank.mode === 'dimensions') {
                const span = isCylinder
                    ? toNullableNumber(this.tank.diameter)
                    : toNullableNumber(this.tank.length);
                const height = toNullableNumber(this.tank.height);
                if (span && height && span > 0 && height > 0) {
                    const ratio = clamp(span / height, 0.75, 3.0);
                    if (ratio >= 1.9) {
                        boxW = 272;
                        boxH = clamp(Math.round(boxW / ratio), 78, 138);
                    } else {
                        boxH = 132;
                        boxW = clamp(Math.round(boxH * ratio), 96, 272);
                    }
                }
            } else {
                const bandCode = this.activeTankBand?.code ?? 'medium';
                const preset = BAND_BOX_SIZES[bandCode] ?? BAND_BOX_SIZES.medium;
                boxW = preset.w;
                boxH = preset.h;
            }

            const x = Math.round((viewW - boxW) / 2);
            const y = Math.round((viewH - boxH) / 2) - 4;
            const substrateH = 12;
            const waterY = y + 8;
            const waterH = Math.max(48, boxH - substrateH - 8);
            const zoneH = waterH / 3;

            const zoneCounts = { 0: 0, 1: 0, 2: 0 };
            for (const item of this.items) {
                if (item.area !== 'fauna') {
                    continue;
                }
                const rec = this.recordsByKey[`fauna:${item.id}`];
                if (rec && rec.zone !== null && rec.zone !== undefined && zoneCounts[rec.zone] !== undefined) {
                    zoneCounts[rec.zone] += 1;
                }
            }

            return {
                x,
                y,
                w: boxW,
                h: boxH,
                isCylinder,
                waterY,
                waterH,
                substrateY: y + boxH - substrateH,
                substrateH,
                zones: [
                    { key: 'top', idx: 2, label: 'Górna', y: waterY, h: zoneH, count: zoneCounts[2] },
                    { key: 'middle', idx: 1, label: 'Środkowa', y: waterY + zoneH, h: zoneH, count: zoneCounts[1] },
                    { key: 'bottom', idx: 0, label: 'Dolna', y: waterY + zoneH * 2, h: zoneH, count: zoneCounts[0] },
                ],
                hasFlora: this.selectedCountForArea('flora') > 0,
            };
        },

        zoneLabel(zoneIdx) {
            if (zoneIdx === null || zoneIdx === undefined || !this.bundle) {
                return null;
            }

            return this.bundle.dicts?.zones?.[zoneIdx] ?? null;
        },

        temperamentLabel(tempIdx) {
            if (tempIdx === null || tempIdx === undefined || !this.bundle) {
                return null;
            }

            return this.bundle.dicts?.temperaments?.[tempIdx] ?? null;
        },

        tempRangeLabel(tempIdx) {
            if (tempIdx === null || tempIdx === undefined || !this.bundle) {
                return null;
            }

            return this.bundle.dicts?.tempBands?.[tempIdx]?.rangeLabel ?? null;
        },

        co2Label(co2Idx) {
            if (co2Idx === null || co2Idx === undefined || !this.bundle) {
                return null;
            }

            return this.bundle.dicts?.co2Needs?.[co2Idx] ?? null;
        },

        checkLabel(checkCode) {
            return CHECK_LABELS[checkCode] ?? checkCode;
        },

        checkUnit(checkCode) {
            return CHECK_UNITS[checkCode] ?? 'gatunków';
        },

        severityLabel(severity) {
            if (severity === 'conflict' || severity === 'warning') {
                return 'Uwaga';
            }

            return 'Informacja';
        },

        itemMetaText(area, rec) {
            if (area === 'fauna') {
                return [rec.kind, this.zoneLabel(rec.zone)].filter(Boolean).join(' · ');
            }
            if (area === 'flora') {
                return [rec.plantZone, rec.difficulty].filter(Boolean).join(' · ');
            }
            if (area === 'equipment') {
                return [rec.category, rec.brand || rec.producer].filter(Boolean).join(' · ');
            }

            return [rec.domain, rec.brand || rec.producer].filter(Boolean).join(' · ');
        },

        catalogUrl(area, id) {
            return (this.config.catalogProductUrlTemplate ?? '/katalog/__AREA__/__ID__')
                .replace('__AREA__', encodeURIComponent(area))
                .replace('__ID__', encodeURIComponent(id));
        },

        candidateNote(id) {
            return this.faunaCandidateNotes[id] ?? { hasWarning: false, findings: [], discrepancies: [] };
        },

        resetFaunaFilters() {
            this.faunaFilters = { query: '', zone: null, kind: null, compatOnly: false };
        },

        resetFloraFilters() {
            this.floraFilters = { query: '', plantZone: '', difficulty: '', co2: '' };
        },

        openMobileDialog() {
            if (this.$refs.previewDialog && typeof this.$refs.previewDialog.showModal === 'function') {
                this.$refs.previewDialog.showModal();
            }
        },

        closeMobileDialog() {
            if (this.$refs.previewDialog && typeof this.$refs.previewDialog.close === 'function') {
                this.$refs.previewDialog.close();
            }
        },
    };
}

function registerCreatorComponent() {
    if (!window.Alpine || window.__aquariumCreatorRegistered) {
        return;
    }
    window.__aquariumCreatorRegistered = true;
    window.Alpine.data('aquariumCreator', aquariumCreator);
}

if (window.Alpine) {
    registerCreatorComponent();
}
document.addEventListener('alpine:init', registerCreatorComponent);

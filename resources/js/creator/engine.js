/**
 * Czysty silnik obliczeniowy kreatora akwarium (zero zależności od DOM i frameworków).
 * Wszystkie słowniki, etykiety i progi pochodzą wyłącznie z pakietu JSON (`bundle.dicts`, `bundle.rules`).
 */

const POLISH_FOLD_MAP = {
    ą: 'a', ć: 'c', ę: 'e', ł: 'l', ń: 'n', ó: 'o', ś: 's', ź: 'z', ż: 'z',
    Ą: 'a', Ć: 'c', Ę: 'e', Ł: 'l', Ń: 'n', Ó: 'o', Ś: 's', Ź: 'z', Ż: 'z',
    ø: 'o', Ø: 'o',
};

const CRITERIA_ORDER = ['size', 'fish', 'temperature', 'ph'];

function fold(text) {
    return String(text ?? '')
        .replace(/[ąćęłńóśźżĄĆĘŁŃÓŚŹŻøØ]/g, (ch) => POLISH_FOLD_MAP[ch] ?? ch)
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, '')
        .toLowerCase()
        .trim();
}

function cmpAscii(a, b) {
    if (a < b) {
        return -1;
    }
    if (a > b) {
        return 1;
    }

    return 0;
}

function asciiLower(text) {
    return String(text ?? '').replace(/[A-Z]/g, (ch) => ch.toLowerCase());
}

function parseNumber(value) {
    if (value === null || value === undefined || value === '') {
        return null;
    }
    if (typeof value === 'number') {
        return Number.isFinite(value) ? value : Number.NaN;
    }
    const normalized = String(value).trim().replace(',', '.');
    if (normalized === '') {
        return null;
    }
    const parsed = Number(normalized);

    return Number.isFinite(parsed) ? parsed : Number.NaN;
}

function round2(n) {
    return Math.round((n + 1e-12) * 100) / 100;
}

function fmt(n, decimals = null) {
    if (decimals !== null) {
        return Number(n).toFixed(decimals).replace('.', ',');
    }

    return Number(n)
        .toFixed(2)
        .replace('.', ',')
        .replace(/0+$/, '')
        .replace(/,$/, '');
}

function bandForLiters(liters, rules) {
    const bands = rules?.tankBands ?? [];
    for (const band of bands) {
        if (band.maxLiters === null || liters <= band.maxLiters) {
            return band.code;
        }
    }

    return bands.length > 0 ? bands[bands.length - 1].code : null;
}

function resolveTankBand(bundle, codeOrLabel) {
    if (codeOrLabel === null || codeOrLabel === undefined || codeOrLabel === '') {
        return null;
    }

    return (bundle?.dicts?.tankBands ?? []).find(
        (b) => b.code === codeOrLabel || b.label === codeOrLabel,
    ) ?? null;
}

function resolveTempBand(bundle, codeOrLabel) {
    if (codeOrLabel === null || codeOrLabel === undefined || codeOrLabel === '') {
        return null;
    }

    return (bundle?.dicts?.tempBands ?? []).find(
        (b) => b.code === codeOrLabel || b.label === codeOrLabel,
    ) ?? null;
}

function resolvePhBand(bundle, codeOrLabel) {
    if (codeOrLabel === null || codeOrLabel === undefined || codeOrLabel === '') {
        return null;
    }

    return (bundle?.dicts?.phBands ?? []).find(
        (b) => b.code === codeOrLabel || b.label === codeOrLabel,
    ) ?? null;
}

function resolveFishIndex(bundle, codeOrLabel) {
    if (codeOrLabel === null || codeOrLabel === undefined || codeOrLabel === '') {
        return null;
    }
    const tankBands = bundle?.dicts?.tankBands ?? [];
    const byCode = tankBands.findIndex((b) => b.code === codeOrLabel);
    if (byCode !== -1) {
        return byCode;
    }
    const byLabel = (bundle?.dicts?.fishBands ?? []).indexOf(codeOrLabel);

    return byLabel !== -1 ? byLabel : null;
}

function makeEvidence(column, value, recordId, batch = null) {
    return {
        column,
        value,
        recordId,
        batch,
        text: `baza: ${column} = ${value} (${recordId})`,
    };
}

function invalidVolume(errors) {
    return {
        valid: false,
        errors,
        grossLiters: null,
        netLiters: null,
        savedLiters: null,
        bandLiters: null,
        sizeBand: null,
        formulaSteps: [],
    };
}

/**
 * 1. Przeliczanie objętości i pasma wielkości zbiornika (1:1 z CalculateTankVolume.php).
 */
export function calculateVolume(input, rules) {
    const r = rules?.rules ?? rules ?? {};
    const mode = input?.mode ?? 'band';
    const dimLimits = r?.limits?.dimension_cm ?? { min: 1, max: 1000 };
    const volLimits = r?.limits?.volume_l ?? { min: 1, max: 65535 };

    if (mode === 'band') {
        const band = (r.tankBands ?? []).find((b) => b.code === input?.sizeBand);
        if (!band) {
            return invalidVolume({ sizeBand: 'Wybierz pasmo wielkości zbiornika.' });
        }

        return {
            valid: true,
            errors: {},
            grossLiters: band.volume,
            netLiters: null,
            savedLiters: band.volume,
            bandLiters: band.volume,
            sizeBand: band.code,
            formulaSteps: [],
        };
    }

    if (mode === 'liters') {
        const errors = {};
        const liters = parseNumber(input?.liters);
        if (liters === null) {
            errors.liters = 'Podaj litraż zbiornika.';
        } else if (Number.isNaN(liters) || liters <= 0 || liters < volLimits.min || liters > volLimits.max) {
            errors.liters = `Litraż musi mieścić się w zakresie ${volLimits.min}–${volLimits.max} l.`;
        }

        const deduct = parseNumber(input?.deduct);
        if (deduct !== null && (Number.isNaN(deduct) || deduct < 0 || deduct > volLimits.max)) {
            errors.deduct = `Litry do odjęcia muszą mieścić się w zakresie 0–${volLimits.max} l.`;
        }

        if (Object.keys(errors).length > 0) {
            return invalidVolume(errors);
        }

        let net = null;
        const steps = [`${fmt(liters, 2)} l`];
        if (deduct !== null && deduct > 0) {
            if (deduct >= liters) {
                return invalidVolume({
                    deduct: `Litry do odjęcia muszą być mniejsze niż litraż brutto (${fmt(liters, 2)} l).`,
                });
            }
            net = liters - deduct;
            steps.push(`${fmt(liters, 2)} l − ${fmt(deduct)} l = ${fmt(net, 2)} l`);
        }

        const effective = net ?? liters;
        const bandLiters = Math.floor(effective);

        return {
            valid: true,
            errors: {},
            grossLiters: round2(liters),
            netLiters: net === null ? null : round2(net),
            savedLiters: Math.round(effective),
            bandLiters,
            sizeBand: bandForLiters(bandLiters, r),
            formulaSteps: steps,
        };
    }

    if (mode === 'dimensions') {
        const errors = {};
        const shape = input?.shape ?? 'cuboid';
        if (shape !== 'cuboid' && shape !== 'cylinder') {
            errors.shape = 'Nieprawidłowy kształt zbiornika.';
        }

        const validateDim = (key, label) => {
            const val = parseNumber(input?.[key]);
            if (val === null) {
                errors[key] = `Podaj wartość pola: ${label}.`;

                return null;
            }
            if (Number.isNaN(val) || val <= 0 || val < dimLimits.min || val > dimLimits.max) {
                errors[key] = `${label} musi mieścić się w zakresie ${dimLimits.min}–${dimLimits.max} cm.`;

                return null;
            }

            return val;
        };

        let d = null;
        let l = null;
        let w = null;
        let h = null;

        if (shape === 'cylinder') {
            d = validateDim('diameter', 'Średnica (cm)');
            h = validateDim('height', 'Wysokość lustra wody (cm)');
        } else if (shape === 'cuboid') {
            l = validateDim('length', 'Długość (cm)');
            w = validateDim('width', 'Szerokość (cm)');
            h = validateDim('height', 'Wysokość lustra wody (cm)');
        }

        const deduct = parseNumber(input?.deduct);
        if (deduct !== null && (Number.isNaN(deduct) || deduct < 0 || deduct > volLimits.max)) {
            errors.deduct = `Litry do odjęcia muszą mieścić się w zakresie 0–${volLimits.max} l.`;
        }

        if (Object.keys(errors).length > 0) {
            return invalidVolume(errors);
        }

        let gross;
        let step;
        if (shape === 'cylinder') {
            gross = (Math.PI * ((d / 2) ** 2) * h) / 1000;
            step = `π × (${fmt(d)} ÷ 2)² × ${fmt(h)} cm ÷ 1000 = ${fmt(gross, 2)} l`;
        } else {
            gross = (l * w * h) / 1000;
            step = `${fmt(l)} × ${fmt(w)} × ${fmt(h)} cm = ${fmt(l * w * h)} cm³ ÷ 1000 = ${fmt(gross, 2)} l`;
        }

        const steps = [step];
        let net = null;
        if (deduct !== null && deduct > 0) {
            if (deduct >= gross) {
                return invalidVolume({
                    deduct: `Litry do odjęcia muszą być mniejsze niż litraż brutto (${fmt(gross, 2)} l).`,
                });
            }
            net = gross - deduct;
            steps.push(`${fmt(gross, 2)} l − ${fmt(deduct)} l = ${fmt(net, 2)} l`);
        }

        const effective = net ?? gross;
        const bandLiters = Math.floor(effective);

        return {
            valid: true,
            errors: {},
            grossLiters: round2(gross),
            netLiters: net === null ? null : round2(net),
            savedLiters: Math.round(effective),
            bandLiters,
            sizeBand: bandForLiters(bandLiters, r),
            formulaSteps: steps,
        };
    }

    return invalidVolume({ mode: 'Nieobsługiwany tryb wyliczania litrażu.' });
}

function sizeScore(record, sizeIdx) {
    if (record.anySize) {
        return 3;
    }
    if (!record.tankSizes || record.tankSizes.length === 0) {
        return null;
    }

    let best = 0;
    for (const bandIdx of record.tankSizes) {
        const diff = sizeIdx - bandIdx;
        const score = diff === 0 ? 3 : diff === 1 ? 2 : diff === -1 ? 1 : 0;
        if (score > best) {
            best = score;
        }
    }

    return best;
}

function ordinalScore(valueIdx, wantedIdx) {
    if (valueIdx === null || valueIdx === undefined) {
        return null;
    }
    const gap = Math.abs(valueIdx - wantedIdx);

    return gap === 0 ? 3 : gap === 1 ? 1 : 0;
}

function phScore(record, phIdx) {
    if (!record.ph || record.ph.length === 0) {
        return null;
    }

    let best = 0;
    for (const bandIdx of record.ph) {
        const score = ordinalScore(bandIdx, phIdx);
        if (score !== null && score > best) {
            best = score;
        }
    }

    return best;
}

function sameScores(a, b) {
    const keysA = Object.keys(a);
    const keysB = Object.keys(b);
    if (keysA.length !== keysB.length) {
        return false;
    }

    return keysA.every((key) => a[key] === b[key]);
}

/**
 * 2. Ranking fauny (1:1 z TankEngine.php).
 */
export function rankFauna(bundle, profile) {
    const tankBand = resolveTankBand(bundle, profile?.sizeBand);
    if (!tankBand) {
        return {
            ranked: [],
            topCount: 1,
            decidedBy: null,
            excluded: 0,
            noData: 0,
            effectiveFishBand: null,
            inferredFish: true,
        };
    }

    const sizeIdx = tankBand.index;
    const explicitFishIdx = resolveFishIndex(bundle, profile?.fishBand);
    const inferredFish = explicitFishIdx === null;
    const fishIdx = inferredFish ? sizeIdx : explicitFishIdx;
    const effectiveFishBand = bundle.dicts.tankBands[fishIdx]?.code ?? tankBand.code;

    const tempBand = resolveTempBand(bundle, profile?.tempBand);
    const phBand = resolvePhBand(bundle, profile?.phBand);
    const hasType = profile?.type !== null && profile?.type !== undefined && profile?.type !== '';
    const typeIdx = hasType ? bundle.dicts.tankTypes.indexOf(profile.type) : null;

    const criteria = ['size', 'fish'];
    if (tempBand !== null) {
        criteria.push('temperature');
    }
    if (phBand !== null) {
        criteria.push('ph');
    }

    const ruleWeights = bundle.rules.weights;
    const weights = {};
    criteria.forEach((criterion, index) => {
        weights[criterion] = ruleWeights[index];
    });

    const maxFishGap = bundle.rules.maxFishGap;
    const candidates = [];
    let excluded = 0;
    let noData = 0;

    for (const record of bundle.fauna ?? []) {
        const typeMismatch = typeIdx !== null && !record.anyType && !record.tankTypes.includes(typeIdx);
        const tooBig = record.size !== null && record.size - sizeIdx >= maxFishGap;
        if (typeMismatch || tooBig) {
            excluded++;
            continue;
        }

        const scores = {};
        const sSize = sizeScore(record, sizeIdx);
        if (sSize !== null) {
            scores.size = sSize;
        }
        const sFish = ordinalScore(record.size, fishIdx);
        if (sFish !== null) {
            scores.fish = sFish;
        }
        if (tempBand !== null) {
            const sTemp = ordinalScore(record.temp, tempBand.index);
            if (sTemp !== null) {
                scores.temperature = sTemp;
            }
        }
        if (phBand !== null) {
            const sPh = phScore(record, phBand.index);
            if (sPh !== null) {
                scores.ph = sPh;
            }
        }

        let weight = 0;
        let points = 0;
        for (const [key, score] of Object.entries(scores)) {
            if (weights[key] !== undefined) {
                weight += weights[key];
                points += score * weights[key];
            }
        }

        if (weight === 0) {
            noData++;
            continue;
        }

        const maxPoints = 3 * weight;
        const percent = Math.round((100 * points) / maxPoints);
        candidates.push({
            id: record.id,
            latin: record.latin ?? '',
            percent,
            points,
            maxPoints,
            scores,
        });
    }

    candidates.sort((a, b) => {
        if (b.percent !== a.percent) {
            return b.percent - a.percent;
        }
        for (const criterion of CRITERIA_ORDER) {
            const diff = (b.scores[criterion] ?? -1) - (a.scores[criterion] ?? -1);
            if (diff !== 0) {
                return diff;
            }
        }
        const latinDiff = cmpAscii(asciiLower(a.latin), asciiLower(b.latin));
        if (latinDiff !== 0) {
            return latinDiff;
        }

        return cmpAscii(a.id, b.id);
    });

    let topCount = 0;
    if (candidates.length > 0) {
        const leader = candidates[0];
        for (const candidate of candidates) {
            if (candidate.percent !== leader.percent || !sameScores(candidate.scores, leader.scores)) {
                break;
            }
            topCount++;
        }
    }

    let decidedBy = null;
    if (topCount === 1 && candidates.length > 1 && candidates[1].percent === candidates[0].percent) {
        decidedBy = CRITERIA_ORDER.find(
            (c) => (candidates[0].scores[c] ?? -1) !== (candidates[1].scores[c] ?? -1),
        ) ?? null;
    }

    return {
        ranked: candidates.map(({ id, percent, points, maxPoints, scores }) => ({
            id,
            percent,
            points,
            maxPoints,
            scores,
        })),
        topCount: Math.max(topCount, 1),
        decidedBy,
        excluded,
        noData,
        effectiveFishBand,
        inferredFish,
    };
}

function indexById(items) {
    const map = new Map();
    for (const item of items ?? []) {
        map.set(item.id, item);
    }

    return map;
}

function hasCo2Equipment(selectedItems, equipmentById) {
    for (const item of selectedItems ?? []) {
        if (item.area === 'equipment' && (item.qty ?? 1) > 0) {
            const eq = equipmentById.get(item.id);
            if (eq && eq.category === 'Dozowanie CO2') {
                return true;
            }
        }
    }

    return false;
}

/**
 * 3. Zgodność obsady według katalogu (1:1 z CompatibilityEngine.php + rozbieżności tabela–opis).
 */
export function checkCompatibility(bundle, tankContext, selectedItems, candidate = null) {
    const faunaById = indexById(bundle.fauna);
    const floraById = indexById(bundle.flora);
    const equipmentById = indexById(bundle.equipment);

    const tankSize = resolveTankBand(bundle, tankContext?.sizeBand);
    const tankType = tankContext?.type !== null && tankContext?.type !== undefined && tankContext?.type !== ''
        ? tankContext.type
        : null;
    const tankTypeIdx = tankType !== null ? bundle.dicts.tankTypes.indexOf(tankType) : null;
    const tankTemp = resolveTempBand(bundle, tankContext?.tempBand);
    const tankPh = resolvePhBand(bundle, tankContext?.phBand);
    const co2Dosing = tankContext?.co2Dosing !== undefined && tankContext?.co2Dosing !== null
        ? Boolean(tankContext.co2Dosing)
        : hasCo2Equipment(selectedItems, equipmentById);

    const stock = [];
    let unknown = 0;

    for (const item of selectedItems ?? []) {
        if ((item.qty ?? 1) <= 0) {
            continue;
        }
        if (item.area === 'fauna') {
            const rec = faunaById.get(item.id);
            if (rec) {
                stock.push({ area: 'fauna', record: rec });
            } else {
                unknown++;
            }
        } else if (item.area === 'flora') {
            const rec = floraById.get(item.id);
            if (rec) {
                stock.push({ area: 'flora', record: rec });
            } else {
                unknown++;
            }
        }
    }

    let subject = null;
    if (candidate !== null && candidate !== undefined) {
        const rec = candidate.area === 'fauna'
            ? faunaById.get(candidate.id)
            : candidate.area === 'flora'
                ? floraById.get(candidate.id)
                : null;
        if (!rec) {
            return {
                assessed: tankSize !== null,
                verdict: 'clear',
                findings: [],
                rows: [],
                unknown,
                discrepancies: [],
            };
        }
        subject = { area: candidate.area, record: rec };
    }

    const dicts = bundle.dicts;
    const tankLabel = tankSize ? tankSize.label : 'brak danych';
    const tankEvidence = (column, value) => makeEvidence(column, value, 'zbiornik', null);
    const phLabels = (indices) => indices.map((i) => dicts.phBands[i].label).join('; ');
    const co2RequiredIdx = dicts.co2Needs.indexOf('Niezbędny');
    const findDiscrepancy = (record, column) => (record.discrepancies ?? []).find((d) => d.column === column) ?? null;

    const tankChecks = (entry) => {
        const r = entry.record;
        const out = [];

        const hasCatalogSize = r.anySize || r.tankSizes.length > 0;
        const hasSizeData = tankSize !== null && hasCatalogSize;
        const smaller = tankSize !== null && !r.anySize && r.tankSizes.length > 0
            && tankSize.index < Math.min(...r.tankSizes);
        const sizeLabels = r.anySize
            ? 'Wszystkie'
            : hasCatalogSize
                ? r.tankSizes.map((i) => dicts.tankBands[i].label).join('; ')
                : 'brak danych';
        const sizeEvidence = hasCatalogSize ? [makeEvidence('WielkośćAkwarium', sizeLabels, r.id, null)] : [];
        if (tankSize !== null) {
            sizeEvidence.push(tankEvidence('Pasmo zbiornika', tankLabel));
        }
        const sizeNote = smaller ? ' Wybrane pasmo jest niższe od pasm w katalogu.' : '';
        out.push([
            'tank_too_small',
            hasSizeData,
            {
                ids: [r.id],
                text: `${r.name} — wielkość akwarium w katalogu: ${sizeLabels}. Wybrane pasmo zbiornika: ${tankLabel}.${sizeNote}`,
                evidence: sizeEvidence,
                severity: smaller ? null : 'info',
            },
        ]);

        const hasCatalogTypes = r.anyType || r.tankTypes.length > 0;
        const hasTypeData = tankType !== null && hasCatalogTypes;
        let typeFound = null;
        if (hasTypeData && !r.anyType && !r.tankTypes.includes(tankTypeIdx)) {
            const types = r.tankTypes.map((i) => dicts.tankTypes[i]).join('; ');
            typeFound = {
                ids: [r.id],
                text: `Typy akwarium w katalogu dla ${r.name}: ${types}. Wybrany typ zbiornika: ${tankType}.`,
                evidence: [makeEvidence('TypAkwarium', types, r.id, null), tankEvidence('Typ zbiornika', tankType)],
                severity: null,
            };
        }
        out.push(['tank_type', hasTypeData, typeFound]);

        if (entry.area === 'fauna') {
            const tempMismatch = findDiscrepancy(r, 'Temperatura');
            const hasTempData = r.temp !== null && tankTemp !== null && tempMismatch === null;
            let tempFound = null;
            if (tankTemp !== null && tempMismatch !== null) {
                const tableValue = r.temp !== null ? dicts.tempBands[r.temp].label : '';
                tempFound = {
                    ids: [r.id],
                    text: `Temperatura — brak danych do porównania z ustawieniem zbiornika dla ${r.name}. Źródła w katalogu się różnią: pasmo w tabeli ${tempMismatch.bandRange}, opis „${tempMismatch.excerpt}”.`,
                    evidence: [makeEvidence('Temperatura', tableValue, r.id, null), makeEvidence('Opis', tempMismatch.excerpt, r.id, null)],
                    severity: 'info',
                };
            } else if (tempMismatch === null && r.temp !== null && tankTemp !== null && r.temp !== tankTemp.index) {
                const rTemp = dicts.tempBands[r.temp];
                tempFound = {
                    ids: [r.id],
                    text: `Pasmo temperatury w katalogu dla ${r.name}: ${rTemp.rangeLabel}. Wybrane pasmo zbiornika: ${tankTemp.rangeLabel}.`,
                    evidence: [makeEvidence('Temperatura', rTemp.label, r.id, null), tankEvidence('Temperatura zbiornika', tankTemp.label)],
                    severity: null,
                };
            }
            out.push(['temperature_tank', hasTempData, tempFound]);

            const phMismatch = findDiscrepancy(r, 'pH');
            const hasPhData = r.ph.length > 0 && !r.unknownPh && tankPh !== null && phMismatch === null;
            let phFound = null;
            if (tankPh !== null && phMismatch !== null) {
                phFound = {
                    ids: [r.id],
                    text: `pH — brak danych do porównania z ustawieniem zbiornika dla ${r.name}. Źródła w katalogu się różnią: pasmo w tabeli ${phMismatch.bandRange}, opis „${phMismatch.excerpt}”.`,
                    evidence: [makeEvidence('pH', phLabels(r.ph), r.id, null), makeEvidence('Opis', phMismatch.excerpt, r.id, null)],
                    severity: 'info',
                };
            } else if (phMismatch === null && r.ph.length > 0 && !r.unknownPh && tankPh !== null && !r.ph.includes(tankPh.index)) {
                const values = phLabels(r.ph);
                phFound = {
                    ids: [r.id],
                    text: `Pasma pH w katalogu dla ${r.name}: ${values}. Wybrane pasmo zbiornika: ${tankPh.label}.`,
                    evidence: [makeEvidence('pH', values, r.id, null), tankEvidence('pH zbiornika', tankPh.label)],
                    severity: null,
                };
            }
            out.push(['ph_tank', hasPhData, phFound]);

            const temperament = r.temperament !== null ? dicts.temperaments[r.temperament] : null;
            out.push([
                'temperament',
                temperament !== null,
                {
                    ids: [r.id],
                    text: `${r.name} — temperament w katalogu: ${temperament ?? 'brak danych'}.`,
                    evidence: temperament === null ? [] : [makeEvidence('Temperament', temperament, r.id, null)],
                    severity: 'info',
                },
            ]);
        } else {
            let co2Found = null;
            if (r.co2 === co2RequiredIdx && !co2Dosing) {
                const co2Label = dicts.co2Needs[r.co2];
                co2Found = {
                    ids: [r.id],
                    text: `Baza wskazuje CO2 „${co2Label}” dla ${r.name}. W sprzęcie zbiornika nie ma pozycji z kategorii „Dozowanie CO2”.`,
                    evidence: [makeEvidence('CO2', co2Label, r.id, null), tankEvidence('Sprzęt zbiornika', 'brak pozycji z kategorii Dozowanie CO2')],
                    severity: null,
                };
            }
            out.push(['co2_missing', r.co2 !== null, co2Found]);
        }

        return out;
    };

    const pairMismatchFinding = (ids, column, a, mismatchA, b, mismatchB) => {
        const details = [];
        const evidence = [];
        for (const [record, mismatch] of [[a, mismatchA], [b, mismatchB]]) {
            if (mismatch === null) {
                continue;
            }
            const tableValue = column === 'pH'
                ? phLabels(record.ph)
                : (record.temp !== null ? dicts.tempBands[record.temp].label : '');
            details.push(`${record.name} (pasmo w tabeli: ${mismatch.bandRange}, opis: „${mismatch.excerpt}”)`);
            evidence.push(makeEvidence(column, tableValue, record.id, null));
            evidence.push(makeEvidence('Opis', mismatch.excerpt, record.id, null));
        }

        return {
            ids,
            text: `${column} — brak danych do porównania (${a.name} i ${b.name}). Źródła w katalogu się różnią: ${details.join('; ')}.`,
            evidence,
            severity: 'info',
        };
    };

    const pairChecks = (entryA, entryB) => {
        if (entryA.area !== 'fauna' || entryB.area !== 'fauna') {
            return [];
        }
        const a = entryA.record;
        const b = entryB.record;
        const ids = [a.id, b.id];
        const out = [];

        const tempMismatchA = findDiscrepancy(a, 'Temperatura');
        const tempMismatchB = findDiscrepancy(b, 'Temperatura');
        if (tempMismatchA !== null || tempMismatchB !== null) {
            out.push(['temperature_pair', false, pairMismatchFinding(ids, 'Temperatura', a, tempMismatchA, b, tempMismatchB)]);
        } else {
            const hasTempData = a.temp !== null && b.temp !== null;
            let tempFound = null;
            if (hasTempData && a.temp !== b.temp) {
                const tempA = dicts.tempBands[a.temp];
                const tempB = dicts.tempBands[b.temp];
                tempFound = {
                    ids,
                    text: `Pasma temperatury w katalogu się różnią: ${a.name} (${tempA.rangeLabel}), ${b.name} (${tempB.rangeLabel}).`,
                    evidence: [makeEvidence('Temperatura', tempA.label, a.id, null), makeEvidence('Temperatura', tempB.label, b.id, null)],
                    severity: null,
                };
            }
            out.push(['temperature_pair', hasTempData, tempFound]);
        }

        const phMismatchA = findDiscrepancy(a, 'pH');
        const phMismatchB = findDiscrepancy(b, 'pH');
        if (phMismatchA !== null || phMismatchB !== null) {
            out.push(['ph_pair', false, pairMismatchFinding(ids, 'pH', a, phMismatchA, b, phMismatchB)]);
        } else {
            const hasPhData = a.ph.length > 0 && b.ph.length > 0 && !a.unknownPh && !b.unknownPh;
            let phFound = null;
            if (hasPhData && !a.ph.some((idx) => b.ph.includes(idx))) {
                const pa = phLabels(a.ph);
                const pb = phLabels(b.ph);
                phFound = {
                    ids,
                    text: `Pasma pH w katalogu się różnią: ${a.name} (${pa}), ${b.name} (${pb}).`,
                    evidence: [makeEvidence('pH', pa, a.id, null), makeEvidence('pH', pb, b.id, null)],
                    severity: null,
                };
            }
            out.push(['ph_pair', hasPhData, phFound]);
        }

        return out;
    };

    const outcomes = [];
    for (const entry of subject === null ? stock : [subject]) {
        outcomes.push(...tankChecks(entry));
    }
    if (subject === null) {
        for (let i = 0; i < stock.length; i++) {
            for (let j = i + 1; j < stock.length; j++) {
                outcomes.push(...pairChecks(stock[i], stock[j]));
            }
        }
    } else {
        for (const other of stock) {
            if (other.record.id !== subject.record.id) {
                outcomes.push(...pairChecks(subject, other));
            }
        }
    }

    const severityMap = bundle.rules.compatSeverity ?? {};
    const stats = {};
    for (const check of bundle.rules.compatChecks ?? []) {
        if ((severityMap[check] ?? 'off') !== 'off') {
            stats[check] = { check, withData: 0, missingData: 0 };
        }
    }

    const findings = [];
    for (const [check, hasData, found] of outcomes) {
        if (!stats[check]) {
            continue;
        }
        if (hasData) {
            stats[check].withData++;
        } else {
            stats[check].missingData++;
        }
        if (found !== null) {
            const severity = found.severity ?? severityMap[check];
            const sortedIds = [...found.ids].sort(cmpAscii);
            findings.push({
                key: `${check}:${sortedIds.join(':')}`,
                check,
                severity,
                ids: found.ids,
                text: found.text,
                evidence: found.evidence,
            });
        }
    }

    const rows = Object.values(stats);
    let verdict = 'clear';
    if (findings.some((f) => f.severity === 'conflict')) {
        verdict = 'conflicts';
    } else if (findings.some((f) => f.severity === 'warning')) {
        verdict = 'notes';
    }

    const discrepancies = [];
    const seenDiscrepancyIds = new Set();
    const checkedEntries = subject === null ? stock : [subject];
    for (const entry of checkedEntries) {
        if (entry.area !== 'fauna' || seenDiscrepancyIds.has(entry.record.id)) {
            continue;
        }
        seenDiscrepancyIds.add(entry.record.id);
        const r = entry.record;
        for (const d of r.discrepancies ?? []) {
            const bandLabel = d.column === 'pH'
                ? phLabels(r.ph)
                : r.temp !== null
                    ? dicts.tempBands[r.temp].label
                    : '';
            discrepancies.push({
                id: r.id,
                name: r.name,
                column: d.column,
                text: `${r.name} — ${d.column}: brak danych do porównania. Źródła w katalogu się różnią — sprawdź u sprzedawcy (pasmo w katalogu: ${d.bandRange}, opis: „${d.excerpt}”).`,
                evidence: [
                    makeEvidence(d.column, bandLabel, r.id, bundle.batchId ?? null),
                    makeEvidence('Opis', d.excerpt, r.id, bundle.batchId ?? null),
                ],
            });
        }
    }

    return {
        assessed: tankSize !== null,
        verdict,
        findings,
        rows,
        unknown,
        discrepancies,
    };
}

/**
 * 4. Filtrowanie flory dla pasma i typu zbiornika oraz opcjonalnych filtrów użytkownika.
 */
export function filterFlora(bundle, tankContext, filters = {}) {
    const tankSize = resolveTankBand(bundle, tankContext?.sizeBand);
    const sizeIdx = tankSize ? tankSize.index : null;
    const tankType = tankContext?.type !== null && tankContext?.type !== undefined && tankContext?.type !== ''
        ? tankContext.type
        : null;
    const typeIdx = tankType !== null ? bundle.dicts.tankTypes.indexOf(tankType) : null;

    const f = filters ?? {};
    const plantZone = f.plantZone || null;
    const difficulty = f.difficulty || null;
    let co2Idx = null;
    if (f.co2 !== null && f.co2 !== undefined && f.co2 !== '') {
        if (typeof f.co2 === 'number') {
            co2Idx = f.co2;
        } else {
            const byLabel = bundle.dicts.co2Needs.indexOf(f.co2);
            co2Idx = byLabel !== -1 ? byLabel : null;
        }
    }
    const query = f.query ? fold(f.query) : '';

    return (bundle.flora ?? []).filter((item) => {
        if (sizeIdx !== null && !item.anySize && !item.tankSizes.includes(sizeIdx)) {
            return false;
        }
        if (typeIdx !== null && !item.anyType && !item.tankTypes.includes(typeIdx)) {
            return false;
        }
        if (plantZone !== null && item.plantZone !== plantZone) {
            return false;
        }
        if (difficulty !== null && item.difficulty !== difficulty) {
            return false;
        }
        if (co2Idx !== null && item.co2 !== co2Idx) {
            return false;
        }
        if (query !== '' && !(item.search ?? '').includes(query)) {
            return false;
        }

        return true;
    });
}

/**
 * 5. Kwalifikacja 4 kategorii sprzętu oraz wskazówki z dowodami (bez słów „potrzebujesz / obowiązkowy”).
 */
export function recommendEquipment(bundle, tankContext, selectedItems) {
    const tankSize = resolveTankBand(bundle, tankContext?.sizeBand);
    const sizeIdx = tankSize ? tankSize.index : null;
    const tankType = tankContext?.type !== null && tankContext?.type !== undefined && tankContext?.type !== ''
        ? tankContext.type
        : null;
    const typeIdx = tankType !== null ? bundle.dicts.tankTypes.indexOf(tankType) : null;

    const faunaById = indexById(bundle.fauna);
    const floraById = indexById(bundle.flora);
    const dicts = bundle.dicts;
    const batchId = bundle.batchId ?? null;

    const selectedFauna = [];
    const selectedFlora = [];
    for (const item of selectedItems ?? []) {
        if ((item.qty ?? 1) <= 0) {
            continue;
        }
        if (item.area === 'fauna') {
            const rec = faunaById.get(item.id);
            if (rec) {
                selectedFauna.push(rec);
            }
        } else if (item.area === 'flora') {
            const rec = floraById.get(item.id);
            if (rec) {
                selectedFlora.push(rec);
            }
        }
    }

    const matchingEquipment = (bundle.equipment ?? []).filter((eq) => {
        if (sizeIdx !== null && !eq.anySize && !eq.tankSizes.includes(sizeIdx)) {
            return false;
        }
        if (typeIdx !== null && !eq.anyType && !eq.tankTypes.includes(typeIdx)) {
            return false;
        }

        return true;
    });

    const faunaWithTemp = selectedFauna.filter((r) => r.temp !== null);
    let heatingHint = { status: 'none', text: null, evidence: [] };
    if (faunaWithTemp.length > 0) {
        const warmOrHot = faunaWithTemp.filter((r) => r.temp >= 2);
        const cool = faunaWithTemp.filter((r) => r.temp === 1);
        if (warmOrHot.length > 0) {
            const labels = [...new Set(warmOrHot.map((r) => dicts.tempBands[r.temp].label))];
            heatingHint = {
                status: 'recommended',
                text: `Wskazana (baza: ${labels.join(', ')})`,
                evidence: warmOrHot.map((r) => makeEvidence('Temperatura', dicts.tempBands[r.temp].label, r.id, batchId)),
            };
        } else if (cool.length > 0) {
            const label = dicts.tempBands[1].label;
            heatingHint = {
                status: 'conditional',
                text: `Zależnie od pomieszczenia (baza: ${label})`,
                evidence: cool.map((r) => makeEvidence('Temperatura', dicts.tempBands[r.temp].label, r.id, batchId)),
            };
        } else {
            const label = dicts.tempBands[0].label;
            heatingHint = {
                status: 'not_indicated',
                text: `Baza nie wskazuje grzałki (baza: ${label})`,
                evidence: faunaWithTemp.map((r) => makeEvidence('Temperatura', dicts.tempBands[r.temp].label, r.id, batchId)),
            };
        }
    }

    const co2RequiredIdx = dicts.co2Needs.indexOf('Niezbędny');
    const co2RecommendedIdx = dicts.co2Needs.indexOf('Wskazany');
    const requiredFlora = selectedFlora.filter((r) => r.co2 === co2RequiredIdx);
    const recommendedFlora = selectedFlora.filter((r) => r.co2 === co2RecommendedIdx);

    let co2Hint = { status: 'none', text: null, evidence: [] };
    if (requiredFlora.length > 0) {
        const label = dicts.co2Needs[co2RequiredIdx];
        co2Hint = {
            status: 'required',
            text: `Niezbędne dla: ${requiredFlora.map((r) => r.name).join(', ')}`,
            evidence: requiredFlora.map((r) => makeEvidence('CO2', label, r.id, batchId)),
        };
    } else if (recommendedFlora.length > 0) {
        const label = dicts.co2Needs[co2RecommendedIdx];
        co2Hint = {
            status: 'recommended',
            text: `Wskazane dla: ${recommendedFlora.map((r) => r.name).join(', ')}`,
            evidence: recommendedFlora.map((r) => makeEvidence('CO2', label, r.id, batchId)),
        };
    }

    const hintForCategory = (category) => {
        if (category === 'Ogrzewanie') {
            return heatingHint.status === 'none' ? null : heatingHint;
        }
        if (category === 'Dozowanie CO2') {
            return co2Hint.status === 'none' ? null : co2Hint;
        }

        return null;
    };

    const categories = (bundle.rules.equipmentCategories ?? []).map((category) => ({
        category,
        items: matchingEquipment.filter((eq) => eq.category === category),
        hint: hintForCategory(category),
    }));

    return {
        categories,
        hints: {
            heating: heatingHint,
            co2: co2Hint,
        },
        missingSpecsNote: bundle.rules.equipmentMissingSpecsNote ?? '',
    };
}

/**
 * 6. Grupowanie pielęgnacji: start, karmienie (z jawnym brakiem danych dla ślimaków), rośliny, testy wody.
 */
export function recommendCare(bundle, selectedItems) {
    const faunaById = indexById(bundle.fauna);
    const floraById = indexById(bundle.flora);
    const cfg = bundle.rules.careGroups ?? {};

    let hasFish = false;
    let hasCrustacean = false;
    let hasSnail = false;
    let hasFlora = false;

    for (const item of selectedItems ?? []) {
        if ((item.qty ?? 1) <= 0) {
            continue;
        }
        if (item.area === 'fauna') {
            const rec = faunaById.get(item.id);
            if (!rec) {
                continue;
            }
            if (rec.kind === 'Ryba') {
                hasFish = true;
            } else if (rec.kind === 'Skorupiak') {
                hasCrustacean = true;
            } else if (rec.kind === 'Ślimak') {
                hasSnail = true;
            }
        } else if (item.area === 'flora' && floraById.has(item.id)) {
            hasFlora = true;
        }
    }

    const feedingDomains = [];
    if (hasFish && cfg.fishFoodDomain) {
        feedingDomains.push(cfg.fishFoodDomain);
    }
    if (hasCrustacean && cfg.shrimpFoodDomain) {
        feedingDomains.push(cfg.shrimpFoodDomain);
    }

    const care = bundle.care ?? [];
    const start = care.filter((c) => c.taskCategory === cfg.startTaskCategory);
    const feedingItems = feedingDomains.length === 0
        ? []
        : care.filter((c) => feedingDomains.includes(c.domain));
    const plants = hasFlora
        ? care.filter((c) => (cfg.plantDomains ?? []).includes(c.domain))
        : [];
    const testing = care.filter((c) => c.taskKind === cfg.testingTaskKind);
    const snailFoodNote = hasSnail ? (cfg.snailFoodMissingText ?? '') : null;

    return {
        start,
        feeding: {
            items: feedingItems,
            domains: feedingDomains,
            snailFoodMissing: hasSnail,
            snailFoodNote,
            notes: hasSnail && snailFoodNote ? [snailFoodNote] : [],
        },
        plants,
        testing,
        snailFoodMissing: hasSnail,
        snailFoodNote,
    };
}

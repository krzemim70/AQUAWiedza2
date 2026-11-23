<?php

use App\Domain\Advisor\Contracts\Compatibility;
use App\Domain\Advisor\Engine\Compatibility\Check;
use App\Domain\Advisor\Engine\Compatibility\CompatibilityEngine;
use App\Domain\Advisor\Engine\Compatibility\CompatibilityReport;
use App\Domain\Advisor\Engine\Compatibility\Severity;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\Compatibility\TankContext;
use App\Domain\Advisor\Engine\Compatibility\Verdict;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\FaunaPool;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;

/*
 * Złote przypadki zgodności obsady (projekt nowych funkcji, §2 i §14.4) na tests/Fixtures/KnowledgeBase.
 */
const COMPAT_TYPE = 'Ogólne / Towarzyskie';

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

function compatFauna(string $id): StockLine
{
    return new StockLine(GuideArea::Fauna, $id);
}

function compatFlora(string $id): StockLine
{
    return new StockLine(GuideArea::Flora, $id);
}

/** @param list<StockLine> $stock */
function compatFit(TankBand $size, array $stock, ?StockLine $candidate = null, bool $co2 = false): CompatibilityReport
{
    return app(Compatibility::class)->check(new TankContext($size, COMPAT_TYPE, co2Dosing: $co2), $stock, $candidate);
}

/** @return list<string> kody kontroli ustaleń */
function compatCodes(CompatibilityReport $report): array
{
    return array_map(fn ($f) => $f->check->value, $report->findings);
}

it('compares the catalogue temperature bands of FN0001 and FN0011 with evidence from both records', function () {
    $report = compatFit(TankBand::Medium, [compatFauna('FN0001')], compatFauna('FN0011'));

    $finding = collect($report->findings)->firstWhere('check', Check::TemperaturePair);
    expect($finding->severity)->toBe(Severity::Warning)
        ->and($finding->key())->toBe('temperature_pair:FN0001:FN0011')
        ->and(collect($finding->evidence)->map->text()->sort()->values()->all())
        ->toBe(['baza: Temperatura = Grzałka często (22-25,5) (FN0001)', 'baza: Temperatura = Grzałka sporadycznie (18-21,5) (FN0011)'])
        ->and($finding->text)->toContain('Pasma temperatury w katalogu się różnią', '22–25,5 °C', '18–21,5 °C')
        ->and($report->verdict())->toBe(Verdict::Notes);
});

it('detects table-description mismatch for FN0001 pH and FN0165 temperature instead of band difference', function () {
    $phReport = compatFit(TankBand::Medium, [compatFauna('FN0001')], compatFauna('FN0011'));
    $phFinding = collect($phReport->findings)->firstWhere('check', Check::PhPair);
    $phRow = collect($phReport->rows)->firstWhere('check', Check::PhPair);

    expect($phFinding)->not->toBeNull()
        ->and($phFinding->severity)->toBe(Severity::Info)
        ->and($phFinding->text)->toContain('brak danych do porównania', '6,8–7,5', 'pH 7,0–8,5')
        ->not->toContain('Pasma pH w katalogu się różnią')
        ->and(collect($phFinding->evidence)->map->text()->all())
        ->toBe(['baza: pH = Woda obojętna (6.8 - 7.5) (FN0001)', 'baza: Opis = pH 7,0–8,5 (FN0001)'])
        ->and([$phRow->checked, $phRow->noData])->toBe([0, 1]);

    $tempReport = compatFit(TankBand::Medium, [compatFauna('FN0001')], compatFauna('FN0165'));
    $tempFinding = collect($tempReport->findings)->firstWhere('check', Check::TemperaturePair);
    $tempRow = collect($tempReport->rows)->firstWhere('check', Check::TemperaturePair);

    expect($tempFinding)->not->toBeNull()
        ->and($tempFinding->severity)->toBe(Severity::Info)
        ->and($tempFinding->text)->toContain('brak danych do porównania', 'poniżej 18 °C', '15–22°C')
        ->not->toContain('Pasma temperatury w katalogu się różnią')
        ->and(collect($tempFinding->evidence)->map->text()->all())
        ->toBe(['baza: Temperatura = Zimnolubne (poniżej 18) (FN0165)', 'baza: Opis = Temperatura około 15–22°C (FN0165)'])
        ->and([$tempRow->checked, $tempRow->noData])->toBe([0, 1]);
});

it('compares different pH and adjacent temperature bands for FN0117 and FN0029', function () {
    $report = compatFit(TankBand::Medium, [compatFauna('FN0117')], compatFauna('FN0029'));

    expect(compatCodes($report))->toContain('ph_pair', 'temperature_pair');
    expect($report->findingsFor(Check::PhPair))->toBe(1)
        ->and(collect($report->findings)->firstWhere('check', Check::PhPair)->severity)->toBe(Severity::Warning)
        ->and(collect($report->findings)->firstWhere('check', Check::TemperaturePair)->severity)->toBe(Severity::Warning);
});

it('reports the two temperament labels without warning about the pair', function () {
    $report = compatFit(TankBand::Small, [compatFauna('FN0001'), compatFauna('FN0090')]);

    $entries = collect($report->findings)->where('check', Check::Temperament)->values();
    expect($entries->pluck('severity')->all())->toBe([Severity::Info, Severity::Info])
        ->and($entries->map(fn ($entry) => $entry->evidence[0]->value)->all())->toBe(['Spokojny', 'Agresywny/Terytorialny']);
});

it('shows the catalogue temperament label of the snail FN0153 as information', function () {
    $report = compatFit(TankBand::Small, [compatFauna('FN0001')], compatFauna('FN0153'));
    $finding = collect($report->findings)->firstWhere('check', Check::Temperament);
    expect($finding->severity)->toBe(Severity::Info)->and($finding->ids)->toBe(['FN0153']);
});

it('FN0078 in a Large tank: tank too small warning, not a conflict', function () {
    $report = compatFit(TankBand::Large, [], compatFauna('FN0078'));

    $finding = collect($report->findings)->firstWhere('check', Check::TankTooSmall);
    expect($finding->severity)->toBe(Severity::Warning)
        ->and($finding->evidence[0]->text())->toBe('baza: WielkośćAkwarium = Bardzo duże (> 500L) (FN0078)')
        ->and(compatCodes($report))->not->toContain('fish_too_large');
});

it('compares the tank-size entry of FN0076 with Nano', function () {
    $report = compatFit(TankBand::Nano, [], compatFauna('FN0076'));

    $finding = collect($report->findings)->firstWhere('check', Check::TankTooSmall);
    expect($finding->severity)->toBe(Severity::Warning)
        ->and($finding->text)->toContain('Wybrane pasmo zbiornika: Nano', 'Wybrane pasmo jest niższe')
        ->and(compatCodes($report))->not->toContain('fish_too_large');
});

it('does not warn about a tank larger than recommended', function () {
    $report = compatFit(TankBand::Huge, [], compatFauna('FN0013'));
    expect(collect($report->findings)->firstWhere('check', Check::TankTooSmall)->severity)->toBe(Severity::Info);
});

it('shows the size entry Wszystkie for FN0150', function () {
    $report = compatFit(TankBand::Nano, [], compatFauna('FN0150'));

    $finding = collect($report->findings)->firstWhere('check', Check::TankTooSmall);
    expect($finding->severity)->toBe(Severity::Info)->and($finding->evidence[0]->value)->toBe('Wszystkie');
});

it('FL0011 (CO2 required) without CO2 dosing gives an info finding, with dosing none', function () {
    $without = compatFit(TankBand::Medium, [compatFlora('FL0011')]);
    $with = compatFit(TankBand::Medium, [compatFlora('FL0011')], co2: true);

    $finding = collect($without->findings)->firstWhere('check', Check::Co2Missing);
    expect($finding->severity)->toBe(Severity::Info)
        ->and($finding->evidence[0]->text())->toBe('baza: CO2 = Niezbędny (FL0011)')
        ->and($without->verdict())->toBe($with->verdict())
        ->and(compatCodes($with))->not->toContain('co2_missing');
});

it('counts the missing tank size while comparing the known bands', function () {
    $report = app(Compatibility::class)->check(new TankContext(null, COMPAT_TYPE), [compatFauna('FN0001'), compatFauna('FN0011')]);

    expect($report->assessed)->toBeFalse()
        ->and(collect($report->rows)->firstWhere('check', Check::TankTooSmall)->noData)->toBe(2)
        ->and($report->findingsFor(Check::TemperaturePair))->toBe(1);
});

it('counts equal temperature bands and notes missing catalogue records', function () {
    $report = compatFit(TankBand::Small, [compatFauna('FN0001'), compatFauna('FN0029'), compatFauna('FN9999')]);

    expect($report->verdict())->toBe(Verdict::Clear)->and($report->unknown)->toBe(1);
    $row = collect($report->rows)->firstWhere('check', Check::TemperaturePair);
    expect([$row->checked, $row->noData])->toBe([1, 0]);
});

it('counts 7357 table temperature differences and marks description mismatches as no-data in Compatibility', function () {
    $recordsWithoutDescriptions = app(FaunaPool::class)->records();
    $severity = array_map(Severity::from(...), app(Settings::class)->get(SettingKey::CompatSeverity));
    $tableOnly = (new CompatibilityEngine($severity))->check(new TankContext(TankBand::Medium, COMPAT_TYPE), $recordsWithoutDescriptions);

    expect(count($recordsWithoutDescriptions))->toBe(200)
        ->and($tableOnly->findingsFor(Check::TemperaturePair))->toBe(7357)
        ->and($tableOnly->findingsFor(Check::PhPair))->toBe(12484);

    $stock = array_map(fn ($r) => compatFauna($r->id), $recordsWithoutDescriptions);
    $withDescriptions = compatFit(TankBand::Medium, $stock);
    $temperature = collect($withDescriptions->rows)->firstWhere('check', Check::TemperaturePair);
    $ph = collect($withDescriptions->rows)->firstWhere('check', Check::PhPair);

    expect($temperature->checked + $temperature->noData)->toBe(19900)
        ->and($ph->checked + $ph->noData)->toBe(19900)
        ->and($temperature->noData)->toBeGreaterThan(0)
        ->and($ph->noData)->toBeGreaterThan(0);
});

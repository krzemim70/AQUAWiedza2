<?php

use App\Domain\Advisor\Contracts\Compatibility;
use App\Domain\Advisor\Engine\Compatibility\Check;
use App\Domain\Advisor\Engine\Compatibility\CompatibilityReport;
use App\Domain\Advisor\Engine\Compatibility\Severity;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\Compatibility\TankContext;
use App\Domain\Advisor\Engine\Compatibility\Verdict;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\FaunaPool;
use App\Domain\KnowledgeBase\Actions\ImportPackage;

/*
 * Złote przypadki zgodności obsady (projekt nowych funkcji, §2) na tests/Fixtures/KnowledgeBase.
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

it('FN0001 and FN0165: temperature conflict (bands 2 and 0) with evidence from both records', function () {
    $report = compatFit(TankBand::Medium, [compatFauna('FN0001')], compatFauna('FN0165'));

    $finding = collect($report->findings)->firstWhere('check', Check::TemperaturePair);
    expect($finding->severity)->toBe(Severity::Conflict)
        ->and($finding->key())->toBe('temperature_pair:FN0001:FN0165')
        ->and(collect($finding->evidence)->map->text()->sort()->values()->all())
        ->toBe(['baza: Temperatura = Grzałka często (22-25,5) (FN0001)', 'baza: Temperatura = Zimnolubne (poniżej 18) (FN0165)'])
        ->and($report->verdict())->toBe(Verdict::Conflicts);
});

it('FN0117 and FN0080: pH conflict, adjacent temperatures give no temperature conflict', function () {
    $report = compatFit(TankBand::Medium, [compatFauna('FN0117')], compatFauna('FN0080'));

    expect(compatCodes($report))->toContain('ph_pair')->not->toContain('temperature_pair');
    expect($report->findingsFor(Check::PhPair))->toBe(1);
});

it('FN0090 next to FN0001: temperament warning only (fish to fish)', function () {
    $report = compatFit(TankBand::Small, [compatFauna('FN0001')], compatFauna('FN0090'));

    $finding = collect($report->findings)->firstWhere('check', Check::Temperament);
    expect($finding->severity)->toBe(Severity::Warning)
        ->and(array_map(fn ($e) => $e->column, $finding->evidence))->toBe(['Temperament', 'Temperament']);
});

it('FN0153 snail with FN0001: no temperament note', function () {
    expect(compatCodes(compatFit(TankBand::Small, [compatFauna('FN0001')], compatFauna('FN0153'))))->not->toContain('temperament');
});

it('FN0078 in a Large tank: tank too small warning, not a conflict', function () {
    $report = compatFit(TankBand::Large, [], compatFauna('FN0078'));

    $finding = collect($report->findings)->firstWhere('check', Check::TankTooSmall);
    expect($finding->severity)->toBe(Severity::Warning)
        ->and($finding->evidence[0]->text())->toBe('baza: WielkośćAkwarium = Bardzo duże (> 500L) (FN0078)')
        ->and(compatCodes($report))->not->toContain('fish_too_large');
});

it('FN0076 in a Nano tank: fish too large conflict (4 - 0 >= 3)', function () {
    $report = compatFit(TankBand::Nano, [], compatFauna('FN0076'));

    expect(collect($report->findings)->firstWhere('check', Check::FishTooLarge)->severity)->toBe(Severity::Conflict);
});

it('does not warn about a tank larger than recommended', function () {
    expect(compatCodes(compatFit(TankBand::Huge, [], compatFauna('FN0013'))))->not->toContain('tank_too_small');
});

it('FN0150 (Wszystkie) in a Nano tank: no size findings', function () {
    $report = compatFit(TankBand::Nano, [], compatFauna('FN0150'));

    expect(compatCodes($report))->not->toContain('tank_too_small')->not->toContain('fish_too_large');
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

it('a tank without a size band is not assessed', function () {
    $report = app(Compatibility::class)->check(new TankContext(null, COMPAT_TYPE), [compatFauna('FN0001'), compatFauna('FN0165')]);

    expect($report->assessed)->toBeFalse();
});

it('a clean stock gives no reservations and counts what was checked', function () {
    $report = compatFit(TankBand::Small, [compatFauna('FN0001'), compatFauna('FN0150')]);

    expect($report->verdict())->toBe(Verdict::Clear)->and($report->findings)->toBe([]);
    $row = collect($report->rows)->firstWhere('check', Check::TemperaturePair);
    expect([$row->checked, $row->noData])->toBe([1, 0]);
});

it('whole-catalogue pairs match the data analysis: 624 temperature and 2584 pH conflicts of 19900 pairs', function () {
    $stock = array_map(fn ($r) => compatFauna($r->id), app(FaunaPool::class)->records());
    $report = compatFit(TankBand::Medium, $stock);

    $temperature = collect($report->rows)->firstWhere('check', Check::TemperaturePair);
    expect(count($stock))->toBe(200)
        ->and($report->findingsFor(Check::TemperaturePair))->toBe(624)
        ->and($report->findingsFor(Check::PhPair))->toBe(2584)
        ->and($temperature->checked + $temperature->noData)->toBe(19900);
});

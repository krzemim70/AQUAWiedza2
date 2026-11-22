<?php

use App\Domain\Advisor\Actions\RunDrop;
use App\Domain\Advisor\Actions\RunTank;
use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\Ranking;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TankProfile;
use App\Domain\Advisor\Engine\TemperatureBand;
use App\Domain\KnowledgeBase\Actions\ImportPackage;

/*
 * Przypadki D01–D03 i T01–T04 z raportu 60 §2.7 na tests/Fixtures/KnowledgeBase (200 rekordów fauny).
 * D04 (wykorzystany limit) sprawdza tests/Feature/Advisor/DropTest, T05 (brak pasma, obcy zbiornik) czeka na moduł Akwarium.
 * Zgodność z oryginalną klasą punktacji Tank sprawdzono na tych danych: dla rekordów z jednym pasmem akwarium
 * wszystkie procenty i zbiór ocenionych rekordów są identyczne.
 */
const TYPE = 'Ogólne / Towarzyskie';

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

/** @return list<string> */
function summary(Ranking $ranking): array
{
    return array_map(fn ($m) => $m->record->id.':'.$m->percent(), $ranking->matches);
}

function expected(string $case): array
{
    return explode(' ', implode(' ', (require __DIR__.'/expected.php')[$case]));
}

it('D01 Nano without preferences: nine equal best fish at 3/100%, FN0060 first', function () {
    $r = app(RunDrop::class)->handle(new TankProfile(TankBand::Nano));

    expect(summary($r))->toBe(expected('D01'))
        ->and($r->matches)->toHaveCount(11)
        ->and($r->topCount)->toBe(9)
        ->and($r->top()->record->id)->toBe('FN0060')
        ->and([$r->top()->points, $r->top()->maxPoints, $r->top()->percent()])->toBe([3, 3, 100])
        ->and($r->fish)->toBe(FishBand::Nano)->and($r->fishDerived)->toBeTrue()->and($r->fishAdjusted)->toBeFalse();
});

it('D02 Small with full input: FN0001 9/100%, eight equal best fish out of 81', function () {
    $r = app(RunDrop::class)->handle(new TankProfile(TankBand::Small, FishBand::Small, TemperatureBand::Warm, PhBand::Neutral));

    expect(summary($r))->toBe(expected('D02'))
        ->and($r->matches)->toHaveCount(81)
        ->and($r->topCount)->toBe(8)
        ->and($r->top()->record->id)->toBe('FN0001')
        ->and([$r->top()->points, $r->top()->percent()])->toBe([9, 100]);
});

it('D03 Nano with a huge fish: fish size moves to Medium, temperature without a match is ignored', function () {
    $r = app(RunDrop::class)->handle(new TankProfile(TankBand::Nano, FishBand::Huge, TemperatureBand::Cold, PhBand::Alkaline));

    expect(summary($r))->toBe(expected('D03'))
        ->and($r->fish)->toBe(FishBand::Medium)->and($r->fishAdjusted)->toBeTrue()
        ->and($r->topCount)->toBe(1)
        ->and(array_map(fn ($m) => [$m->record->id, $m->points, $m->percent()], array_slice($r->matches, 0, 3)))
        ->toBe([['FN0090', 5, 83], ['FN0002', 4, 67], ['FN0099', 3, 50]]);
});

it('T01 Nano: weights 4/3 with derived fish size; 114 rated, 86 excluded; a snail can lead (multi-band field rated by the best band, so FN0049 now ranks first)', function () {
    $r = app(RunTank::class)->handle(new TankProfile(TankBand::Nano, type: TYPE));

    expect(summary($r))->toBe(expected('T01'))
        ->and([count($r->matches), $r->excluded, $r->noData])->toBe([114, 86, 0])
        ->and($r->fishDerived)->toBeTrue()->and($r->fish)->toBe(FishBand::Nano)
        ->and($r->topCount)->toBe(15)
        ->and(array_map(fn ($m) => $m->points.'/'.$m->maxPoints, $r->topGroup()))->each->toBe('21/21')
        ->and(collect($r->topGroup())->pluck('record.id'))->toContain('FN0153', 'FN0157');
});

it('T02 Small, full input: FN0136 30/30 first, 142 rated, 58 excluded', function () {
    $r = app(RunTank::class)->handle(new TankProfile(TankBand::Small, FishBand::Small, TemperatureBand::Warm, PhBand::Neutral, TYPE));

    expect(summary($r))->toBe(expected('T02'))
        ->and([count($r->matches), $r->excluded])->toBe([142, 58])
        ->and($r->top()->record->id)->toBe('FN0136')
        ->and($r->top()->scores)->toBe(['size' => 3, 'fish' => 3, 'temperature' => 3, 'ph' => 3])
        ->and([$r->top()->points, $r->top()->maxPoints])->toBe([30, 30])
        ->and($r->topCount)->toBe(11);
});

it('T03 Medium without temperature: pH is promoted to weight 2, maximum 27; 168 rated, 32 excluded', function () {
    $r = app(RunTank::class)->handle(new TankProfile(TankBand::Medium, null, null, PhBand::Acidic, TYPE));

    expect(summary($r))->toBe(expected('T03'))
        ->and([count($r->matches), $r->excluded])->toBe([168, 32])
        ->and($r->top()->record->id)->toBe('FN0117')
        ->and([$r->top()->points, $r->top()->maxPoints])->toBe([27, 27])
        ->and($r->top()->scores)->toBe(['size' => 3, 'fish' => 3, 'ph' => 3]);
});

it('T04 Large: the multi-band tank field is no longer skipped (PHP gave FN0041 18/18 = 100%, v2 rates it 26/30 = 87%)', function () {
    $r = app(RunTank::class)->handle(new TankProfile(TankBand::Large, FishBand::Small, TemperatureBand::Cool, PhBand::Neutral, TYPE));
    $byId = collect($r->matches)->keyBy('record.id');

    expect(summary($r))->toBe(expected('T04'))
        ->and([$byId['FN0041']->points, $byId['FN0041']->maxPoints, $byId['FN0041']->scores['size']])->toBe([26, 30, 2])
        ->and($byId['FN0042']->percent())->toBe(87)
        ->and(collect($r->topGroup())->pluck('record.id')->all())->toBe(['FN0150', 'FN0154']);
});

it('Tank with a type that no record has returns an empty ranking', function () {
    $r = app(RunTank::class)->handle(new TankProfile(TankBand::Nano, type: 'Nieistniejący typ'));

    expect($r->matches)->toBe([])->and($r->excluded)->toBe(200)->and($r->top())->toBeNull();
});

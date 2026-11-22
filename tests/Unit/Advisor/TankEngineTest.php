<?php

use App\Domain\Advisor\Engine\Criterion;
use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\FishBand;
use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TankBand;
use App\Domain\Advisor\Engine\TankEngine;
use App\Domain\Advisor\Engine\TankProfile;
use App\Domain\Advisor\Engine\TemperatureBand;

function fauna(string $id, string $latin, FishBand $size, TemperatureBand $temperature, PhBand $ph, array $tankSizes = [TankBand::Small]): FaunaRecord
{
    return new FaunaRecord($id, $id, $latin, 'Ryba', $size, $temperature, [$ph], $tankSizes, false, ['Ogólne / Towarzyskie']);
}

it('reports the deciding criterion when two different vectors share a percent', function () {
    // B: akwarium 1, ryba 3, temperatura 3, pH 3 → 4+9+6+3 = 22. C: akwarium 2, ryba 3, temperatura 1, pH 3 → 8+9+2+3 = 22.
    $profile = new TankProfile(TankBand::Small, FishBand::Small, TemperatureBand::Warm, PhBand::Neutral);
    $b = fauna('B', 'b', FishBand::Small, TemperatureBand::Warm, PhBand::Neutral, [TankBand::Medium]);
    $c = fauna('C', 'c', FishBand::Small, TemperatureBand::Cool, PhBand::Neutral, [TankBand::Nano]);

    $r = (new TankEngine)->rank([$c, $b], $profile);

    expect($r->matches[0]->percent())->toBe($r->matches[1]->percent())
        ->and($r->top()->record->id)->toBe('C')
        ->and($r->topCount)->toBe(1)
        ->and($r->decidedBy)->toBe(Criterion::Size);
});

it('groups identical percents with identical vectors and orders them by latin name then id', function () {
    $profile = new TankProfile(TankBand::Small, FishBand::Small, TemperatureBand::Warm, PhBand::Neutral);
    $z = fauna('1', 'zeta', FishBand::Small, TemperatureBand::Warm, PhBand::Neutral);
    $x = fauna('2', 'Alfa', FishBand::Small, TemperatureBand::Warm, PhBand::Neutral);
    $y = fauna('3', 'alfa', FishBand::Small, TemperatureBand::Warm, PhBand::Neutral);

    $r = (new TankEngine)->rank([$z, $y, $x], $profile);

    expect($r->topCount)->toBe(3)->and(array_map(fn ($m) => $m->record->id, $r->matches))->toBe(['2', '3', '1']);
});

it('counts a record without any rated criterion as no data', function () {
    $record = new FaunaRecord('X', 'X', 'x', 'Ryba', null, null, [], [], false, ['Ogólne / Towarzyskie']);

    $r = (new TankEngine)->rank([$record], new TankProfile(TankBand::Small, null, TemperatureBand::Warm, PhBand::Neutral));

    expect($r->matches)->toBe([])->and($r->noData)->toBe(1);
});

<?php

use App\Domain\Aquarium\Calculators\Co2Estimate;
use App\Domain\Aquarium\Calculators\StockingLoad;

it('computes the carbonate estimate from KH and pH', function () {
    expect(Co2Estimate::mgPerLiter(4, 6.8))->toEqualWithDelta(19.0187, 0.0001)
        ->and(Co2Estimate::mgPerLiter(4, 7.2))->toEqualWithDelta(7.5715, 0.0001)
        ->and(Co2Estimate::mgPerLiter(6, 7))->toBe(18.0);
});

it('divides the total supplied adult lengths by net litres', function () {
    $total = StockingLoad::totalCm([['quantity' => 10, 'length' => 4.0], ['quantity' => 6, 'length' => 2.5]]);

    expect($total)->toBe(55.0)
        ->and(StockingLoad::cmPerLiter($total, 63))->toEqualWithDelta(0.8730, 0.0001);
});

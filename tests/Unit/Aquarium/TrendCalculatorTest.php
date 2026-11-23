<?php

use App\Domain\Aquarium\Calculators\TrendCalculator;
use App\Domain\Aquarium\Calculators\TrendChart;
use App\Domain\Aquarium\Data\TrendPoint;
use App\Domain\Aquarium\Models\MeasurementMethod;

it('reports insufficient data for 0 to 2 measurements below minimum', function (int $count) {
    $now = new DateTimeImmutable('2026-12-07 12:00:00', new DateTimeZone('UTC'));
    $points = [];
    for ($i = 0; $i < $count; $i++) {
        $points[] = new TrendPoint($now->modify('-'.($count - $i).' days'), 7.0 + $i * 0.1, MeasurementMethod::Drop);
    }

    $result = TrendCalculator::calculate('ph', $points, windowDays: 30, minPoints: 3, now: $now);

    expect($result->enoughData)->toBeFalse()
        ->and($result->pointCount)->toBe($count)
        ->and($result->delta)->toBeNull()
        ->and($result->ratePerDay)->toBeNull()
        ->and($result->statusMessage)->toBe('Za mało pomiarów do porównania');
})->with([0, 1, 2]);

it('computes change and daily rate for a rising series', function () {
    $now = new DateTimeImmutable('2026-12-07 12:00:00', new DateTimeZone('UTC'));
    $points = [
        new TrendPoint($now->modify('-2 days'), 7.0, MeasurementMethod::Drop),
        new TrendPoint($now->modify('-6 days'), 6.8, MeasurementMethod::Drop),
        new TrendPoint($now, 7.4, MeasurementMethod::Drop),
    ];

    $result = TrendCalculator::calculate('ph', $points, windowDays: 7, minPoints: 3, now: $now);

    expect($result->enoughData)->toBeTrue()
        ->and($result->pointCount)->toBe(3)
        ->and($result->firstValue)->toBe(6.8)
        ->and($result->lastValue)->toBe(7.4)
        ->and($result->delta)->toBe(0.6)
        ->and($result->ratePerDay)->toBe(0.1)
        ->and($result->mixedMethods)->toBeFalse()
        ->and($result->statusMessage)->toBeNull();
});

it('handles a constant series without zero-division in trend and chart layout', function () {
    $now = new DateTimeImmutable('2026-12-07 12:00:00', new DateTimeZone('UTC'));
    $points = [
        new TrendPoint($now->modify('-4 days'), 7.2, MeasurementMethod::Meter),
        new TrendPoint($now->modify('-2 days'), 7.2, MeasurementMethod::Meter),
        new TrendPoint($now, 7.2, MeasurementMethod::Meter),
    ];

    $result = TrendCalculator::calculate('ph', $points, windowDays: 30, minPoints: 3, now: $now);
    $chart = TrendChart::layout($result);

    expect($result->enoughData)->toBeTrue()
        ->and($result->delta)->toBe(0.0)
        ->and($result->ratePerDay)->toBe(0.0)
        ->and($chart)->not->toBeNull()
        ->and($chart['points'])->toHaveCount(3);

    $zeroPoints = [
        new TrendPoint($now->modify('-4 days'), 0.0),
        new TrendPoint($now->modify('-2 days'), 0.0),
        new TrendPoint($now, 0.0),
    ];
    $zeroResult = TrendCalculator::calculate('no2', $zeroPoints, windowDays: 30, minPoints: 3, now: $now);
    $zeroChart = TrendChart::layout($zeroResult);
    expect($zeroChart)->not->toBeNull()->and($zeroChart['yTicks'][0]['value'])->toBe(1.0);
});

it('detects mixed measurement methods and filters by time window', function () {
    $now = new DateTimeImmutable('2026-12-07 12:00:00', new DateTimeZone('UTC'));
    $points = [
        new TrendPoint($now->modify('-40 days'), 6.5, MeasurementMethod::Strip),
        new TrendPoint($now->modify('-10 days'), 7.0, MeasurementMethod::Strip),
        new TrendPoint($now->modify('-5 days'), 7.2, MeasurementMethod::Drop),
        new TrendPoint($now->modify('-1 day'), 7.3, MeasurementMethod::Drop),
        new TrendPoint($now->modify('+1 day'), 9.0, MeasurementMethod::Meter),
    ];

    $win7 = TrendCalculator::calculate('ph', $points, windowDays: 7, minPoints: 3, now: $now);
    $win30 = TrendCalculator::calculate('ph', $points, windowDays: 30, minPoints: 3, now: $now);
    $win90 = TrendCalculator::calculate('ph', $points, windowDays: 90, minPoints: 3, now: $now);

    expect($win7->enoughData)->toBeFalse()->and($win7->pointCount)->toBe(2)
        ->and($win30->enoughData)->toBeTrue()->and($win30->pointCount)->toBe(3)
        ->and($win30->mixedMethods)->toBeTrue()
        ->and($win30->methods)->toBe([MeasurementMethod::Strip, MeasurementMethod::Drop])
        ->and($win90->pointCount)->toBe(4)
        ->and($win90->firstValue)->toBe(6.5);
});

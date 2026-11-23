<?php

use App\Domain\Aquarium\Calculators\SupplyForecast;

it('calculates per-liter dosage forecast from schedule and events', function () {
    $start = new DateTimeImmutable('2026-12-01 08:00:00', new DateTimeZone('UTC'));

    // 250 ml, dawka 5 ml / 100 l w zbiorniku 63 l = 3,15 ml co 7 dni -> 79 zabiegów * 7 = 553 dni
    $initial = SupplyForecast::calculate(
        remainingAmount: 250.0,
        balanceAt: $start,
        doseAmount: 5.0,
        dosePerLiters: 100.0,
        tankVolumeLiters: 63,
        intervalDays: 7,
        now: $start,
    );

    expect($initial->remainingAmount)->toBe(250.0)
        ->and($initial->dosePerUse)->toBe(3.15)
        ->and($initial->daysRemaining)->toBe(553)
        ->and($initial->missingDataReason)->toBeNull()
        ->and($initial->hasSchedule)->toBeTrue();

    // Po 14 dniach bez ręcznych zdarzeń: 2 zaplanowane dawki (6,30 ml) -> 243,70 ml i 77 * 7 = 539 dni
    $after14Days = SupplyForecast::calculate(
        remainingAmount: 250.0,
        balanceAt: $start,
        doseAmount: 5.0,
        dosePerLiters: 100.0,
        tankVolumeLiters: 63,
        intervalDays: 7,
        now: $start->modify('+14 days'),
    );

    expect($after14Days->remainingAmount)->toBe(243.7)
        ->and($after14Days->daysRemaining)->toBe(539);
});

it('calculates fixed-portion food forecast and handles events without double counting', function () {
    $start = new DateTimeImmutable('2026-12-01 08:00:00', new DateTimeZone('UTC'));

    $projection = SupplyForecast::calculate(
        remainingAmount: 100.0,
        balanceAt: $start,
        doseAmount: 2.0,
        dosePerLiters: null,
        tankVolumeLiters: null,
        intervalDays: 1,
        events: [
            ['occurred_at' => $start->modify('+1 day'), 'delta_amount' => -2.0],
        ],
        now: $start->modify('+10 days'),
    );

    expect($projection->dosePerUse)->toBe(2.0)
        ->and($projection->remainingAmount)->toBe(80.0)
        ->and($projection->daysRemaining)->toBe(40)
        ->and($projection->missingDataReason)->toBeNull();
});

it('reports missing tank volume for per-liter dosage without guessing from tank band', function () {
    $start = new DateTimeImmutable('2026-12-01 08:00:00', new DateTimeZone('UTC'));

    $projection = SupplyForecast::calculate(
        remainingAmount: 250.0,
        balanceAt: $start,
        doseAmount: 5.0,
        dosePerLiters: 100.0,
        tankVolumeLiters: null,
        intervalDays: 7,
        now: $start->modify('+14 days'),
    );

    expect($projection->remainingAmount)->toBe(250.0)
        ->and($projection->dosePerUse)->toBeNull()
        ->and($projection->daysRemaining)->toBeNull()
        ->and($projection->missingDataReason)->toBe('brak danych: uzupełnij litraż zbiornika');
});

it('returns remaining state without day forecast when schedule is omitted', function () {
    $start = new DateTimeImmutable('2026-12-01 08:00:00', new DateTimeZone('UTC'));

    $projection = SupplyForecast::calculate(
        remainingAmount: 98.0,
        balanceAt: $start,
        doseAmount: 2.0,
        dosePerLiters: null,
        tankVolumeLiters: 63,
        intervalDays: null,
        now: $start->modify('+30 days'),
    );

    expect($projection->remainingAmount)->toBe(98.0)
        ->and($projection->dosePerUse)->toBe(2.0)
        ->and($projection->daysRemaining)->toBeNull()
        ->and($projection->missingDataReason)->toBeNull()
        ->and($projection->hasSchedule)->toBeFalse();
});

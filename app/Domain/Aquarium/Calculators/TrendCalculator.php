<?php

namespace App\Domain\Aquarium\Calculators;

use App\Domain\Aquarium\Data\TrendPoint;
use App\Domain\Aquarium\Data\TrendResult;
use App\Domain\Aquarium\Models\MeasurementMethod;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Czyste wyliczenie historii zmian pojedynczego parametru w oknie czasowym.
 * Bez prognoz, ocen i zależności od frameworka.
 */
final class TrendCalculator
{
    /**
     * @param  list<TrendPoint>  $points
     */
    public static function calculate(
        string $parameter,
        array $points,
        int $windowDays,
        int $minPoints = 3,
        ?DateTimeInterface $now = null,
    ): TrendResult {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $untilTs = $now->getTimestamp();
        $fromTs = $untilTs - ($windowDays * 86400);

        $inWindow = array_values(array_filter(
            $points,
            fn (TrendPoint $point): bool => $point->measuredAt->getTimestamp() >= $fromTs
                && $point->measuredAt->getTimestamp() <= $untilTs,
        ));

        usort(
            $inWindow,
            fn (TrendPoint $a, TrendPoint $b): int => $a->measuredAt->getTimestamp() <=> $b->measuredAt->getTimestamp(),
        );

        /** @var array<string, MeasurementMethod> $methodMap */
        $methodMap = [];
        $hasUnspecified = false;
        foreach ($inWindow as $point) {
            if ($point->method === null) {
                $hasUnspecified = true;
            } else {
                $methodMap[$point->method->value] = $point->method;
            }
        }
        $methods = array_values($methodMap);
        $mixedMethods = count($methods) > 1;

        $count = count($inWindow);
        $first = $count > 0 ? $inWindow[0] : null;
        $last = $count > 0 ? $inWindow[$count - 1] : null;
        $enoughData = $count >= $minPoints;

        if (! $enoughData) {
            return new TrendResult(
                parameter: $parameter,
                windowDays: $windowDays,
                pointCount: $count,
                minPoints: $minPoints,
                enoughData: false,
                firstValue: $first?->value,
                lastValue: $last?->value,
                delta: null,
                ratePerDay: null,
                mixedMethods: $mixedMethods,
                hasUnspecifiedMethod: $hasUnspecified,
                methods: $methods,
                statusMessage: 'Za mało pomiarów do porównania',
                points: $inWindow,
            );
        }

        $delta = round($last->value - $first->value, 4);
        $elapsedSeconds = $last->measuredAt->getTimestamp() - $first->measuredAt->getTimestamp();
        $ratePerDay = $elapsedSeconds > 0
            ? round(($last->value - $first->value) / ($elapsedSeconds / 86400), 4)
            : null;

        return new TrendResult(
            parameter: $parameter,
            windowDays: $windowDays,
            pointCount: $count,
            minPoints: $minPoints,
            enoughData: true,
            firstValue: $first->value,
            lastValue: $last->value,
            delta: $delta,
            ratePerDay: $ratePerDay,
            mixedMethods: $mixedMethods,
            hasUnspecifiedMethod: $hasUnspecified,
            methods: $methods,
            statusMessage: null,
            points: $inWindow,
        );
    }
}

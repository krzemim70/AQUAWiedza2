<?php

namespace App\Domain\Aquarium\Calculators;

use App\Domain\Aquarium\Data\SupplyProjection;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

/**
 * Czysta prognoza zużycia zapasu z harmonogramu użytkownika i zdarzeń.
 * Dawka na litry bez wpisanego litrażu zwraca jawny brak danych (bez zgadywania z pasma).
 */
final class SupplyForecast
{
    /**
     * @param  list<array{occurred_at: DateTimeInterface, delta_amount: float}>  $events
     */
    public static function calculate(
        float $remainingAmount,
        DateTimeInterface $balanceAt,
        float $doseAmount,
        ?float $dosePerLiters,
        ?int $tankVolumeLiters,
        ?int $intervalDays,
        array $events = [],
        ?DateTimeInterface $now = null,
    ): SupplyProjection {
        $now ??= new DateTimeImmutable('now', new DateTimeZone('UTC'));
        $pending = self::filterAndSortEvents($events, $balanceAt, $now);

        if ($dosePerLiters !== null && ($tankVolumeLiters === null || $tankVolumeLiters <= 0)) {
            $remaining = $remainingAmount;
            foreach ($pending as $event) {
                $remaining = max(0.0, $remaining + $event['delta_amount']);
            }

            return new SupplyProjection(
                remainingAmount: round($remaining, 2),
                dosePerUse: null,
                daysRemaining: null,
                missingDataReason: 'brak danych: uzupełnij litraż zbiornika',
                hasSchedule: $intervalDays !== null && $intervalDays >= 1,
            );
        }

        $rawDose = $dosePerLiters !== null
            ? Dosage::scale($doseAmount, $dosePerLiters, (float) $tankVolumeLiters)
            : $doseAmount;
        $doseRounded = round($rawDose, 2);
        $effectiveDose = $doseRounded > 0.0 ? $doseRounded : $rawDose;

        if ($intervalDays === null || $intervalDays < 1) {
            $remaining = $remainingAmount;
            foreach ($pending as $event) {
                $remaining = max(0.0, $remaining + $event['delta_amount']);
            }

            return new SupplyProjection(
                remainingAmount: round($remaining, 2),
                dosePerUse: $doseRounded,
                daysRemaining: null,
                missingDataReason: null,
                hasSchedule: false,
            );
        }

        $remaining = $remainingAmount;
        $cursor = $balanceAt;

        foreach ($pending as $event) {
            $days = self::wholeDays($cursor, $event['occurred_at']);
            $scheduled = $event['delta_amount'] > 0
                ? intdiv($days, $intervalDays)
                : ($days > 0 ? intdiv($days - 1, $intervalDays) : 0);

            $remaining = max(0.0, $remaining - $scheduled * $effectiveDose);
            $remaining = max(0.0, $remaining + $event['delta_amount']);
            $cursor = $event['occurred_at'];
        }

        $daysSince = self::wholeDays($cursor, $now);
        $scheduled = intdiv($daysSince, $intervalDays);
        $remaining = max(0.0, $remaining - $scheduled * $effectiveDose);
        $remainingRounded = round($remaining, 2);

        $usesLeft = $effectiveDose > 0.0 ? (int) floor(($remainingRounded + 1e-9) / $effectiveDose) : 0;

        return new SupplyProjection(
            remainingAmount: $remainingRounded,
            dosePerUse: $doseRounded,
            daysRemaining: $usesLeft * $intervalDays,
            missingDataReason: null,
            hasSchedule: true,
        );
    }

    /**
     * @param  list<array{occurred_at: DateTimeInterface, delta_amount: float}>  $events
     * @return list<array{occurred_at: DateTimeInterface, delta_amount: float}>
     */
    private static function filterAndSortEvents(array $events, DateTimeInterface $after, DateTimeInterface $until): array
    {
        $afterTs = $after->getTimestamp();
        $untilTs = $until->getTimestamp();

        $filtered = array_values(array_filter(
            $events,
            fn (array $event): bool => $event['occurred_at']->getTimestamp() > $afterTs
                && $event['occurred_at']->getTimestamp() <= $untilTs,
        ));

        usort(
            $filtered,
            fn (array $a, array $b): int => $a['occurred_at']->getTimestamp() <=> $b['occurred_at']->getTimestamp(),
        );

        return $filtered;
    }

    private static function wholeDays(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $seconds = $to->getTimestamp() - $from->getTimestamp();

        return $seconds <= 0 ? 0 : intdiv($seconds, 86400);
    }
}

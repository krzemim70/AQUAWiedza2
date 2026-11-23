<?php

namespace App\Domain\Aquarium\Calculators;

use App\Domain\Aquarium\Data\TrendResult;

/**
 * Czyste wyliczenie współrzędnych dla responsywnego wykresu SVG historii parametru.
 */
final class TrendChart
{
    public const WIDTH = 600;

    public const HEIGHT = 220;

    private const PAD_LEFT = 56;

    private const PAD_RIGHT = 20;

    private const PAD_TOP = 18;

    private const PAD_BOTTOM = 34;

    /**
     * @param  list<array{min: ?float, max: ?float, label: string}>  $stockRanges
     * @return array{
     *     width: int,
     *     height: int,
     *     plotLeft: int,
     *     plotRight: int,
     *     plotTop: int,
     *     plotBottom: int,
     *     yTicks: list<array{y: float, value: float}>,
     *     bands: list<array{y: float, height: float, label: string, hasMin: bool, hasMax: bool}>,
     *     points: list<array{x: float, y: float, value: float}>,
     *     polyline: string
     * }|null
     */
    public static function layout(TrendResult $trend, array $stockRanges = []): ?array
    {
        if ($trend->points === []) {
            return null;
        }

        $values = array_map(fn ($point): float => $point->value, $trend->points);
        $rawMin = min($values);
        $rawMax = max($values);

        $minY = $rawMin;
        $maxY = $rawMax;
        foreach ($stockRanges as $range) {
            if ($range['min'] !== null) {
                $minY = min($minY, $range['min']);
            }
            if ($range['max'] !== null) {
                $maxY = max($maxY, $range['max']);
            }
        }

        if (abs($maxY - $minY) < 1e-9) {
            if ($minY === 0.0) {
                $minY = 0.0;
                $maxY = 1.0;
            } else {
                $pad = max(0.5, abs($minY) * 0.1);
                $minY = $rawMin >= 0.0 ? max(0.0, $minY - $pad) : $minY - $pad;
                $maxY += $pad;
            }
        } else {
            $span = $maxY - $minY;
            $minY -= $span * 0.1;
            $maxY += $span * 0.1;
            if ($rawMin >= 0.0 && $minY < 0.0) {
                $minY = 0.0;
            }
        }

        $plotLeft = self::PAD_LEFT;
        $plotRight = self::WIDTH - self::PAD_RIGHT;
        $plotTop = self::PAD_TOP;
        $plotBottom = self::HEIGHT - self::PAD_BOTTOM;
        $plotW = $plotRight - $plotLeft;
        $plotH = $plotBottom - $plotTop;

        $mapY = static fn (float $value): float => round(
            $plotTop + $plotH - (($value - $minY) / ($maxY - $minY)) * $plotH,
            2,
        );

        $timestamps = array_map(fn ($point): int => $point->measuredAt->getTimestamp(), $trend->points);
        $minTs = min($timestamps);
        $maxTs = max($timestamps);
        $count = count($trend->points);

        $points = [];
        $coords = [];
        foreach ($trend->points as $index => $point) {
            if ($maxTs === $minTs) {
                $x = $count === 1
                    ? round($plotLeft + $plotW / 2, 2)
                    : round($plotLeft + ($index / ($count - 1)) * $plotW, 2);
            } else {
                $x = round($plotLeft + (($point->measuredAt->getTimestamp() - $minTs) / ($maxTs - $minTs)) * $plotW, 2);
            }
            $y = $mapY($point->value);
            $points[] = ['x' => $x, 'y' => $y, 'value' => $point->value];
            $coords[] = "$x,$y";
        }

        $bands = [];
        foreach ($stockRanges as $range) {
            $lo = $range['min'] ?? $minY;
            $hi = $range['max'] ?? $maxY;
            $clampedLo = max($minY, min($maxY, $lo));
            $clampedHi = max($minY, min($maxY, $hi));
            if ($clampedHi < $clampedLo) {
                continue;
            }
            $yTop = $mapY($clampedHi);
            $yBottom = $mapY($clampedLo);
            $bands[] = [
                'y' => $yTop,
                'height' => round(max(2.0, $yBottom - $yTop), 2),
                'label' => $range['label'],
                'hasMin' => $range['min'] !== null,
                'hasMax' => $range['max'] !== null,
            ];
        }

        $midY = ($minY + $maxY) / 2;
        $yTicks = [
            ['y' => $mapY($maxY), 'value' => $maxY],
            ['y' => $mapY($midY), 'value' => $midY],
            ['y' => $mapY($minY), 'value' => $minY],
        ];

        return [
            'width' => self::WIDTH,
            'height' => self::HEIGHT,
            'plotLeft' => $plotLeft,
            'plotRight' => $plotRight,
            'plotTop' => $plotTop,
            'plotBottom' => $plotBottom,
            'yTicks' => $yTicks,
            'bands' => $bands,
            'points' => $points,
            'polyline' => implode(' ', $coords),
        ];
    }
}

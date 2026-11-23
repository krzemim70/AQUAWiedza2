<?php

namespace App\Domain\Advisor\Engine\WaterHints;

use App\Domain\Advisor\Engine\PhBand;
use App\Domain\Advisor\Engine\TemperatureBand;

final readonly class Range
{
    public function __construct(
        private float $min,
        private float $max,
        private bool $includeMin = true,
        private bool $includeMax = true,
    ) {}

    public static function forBand(PhBand|TemperatureBand $band): self
    {
        return match ($band) {
            TemperatureBand::Cold => new self(-INF, 18, includeMax: false),
            TemperatureBand::Cool => new self(18, 21.5),
            TemperatureBand::Warm => new self(22, 25.5),
            TemperatureBand::Hot => new self(26, 30),
            PhBand::Acidic => new self(-INF, 6.8, includeMax: false),
            PhBand::Neutral => new self(6.8, 7.5),
            PhBand::Alkaline => new self(7.5, INF, includeMin: false),
        };
    }

    public static function isBetweenTemperatureBands(float $reading): bool
    {
        return ($reading > 21.5 && $reading < 22) || ($reading > 25.5 && $reading < 26);
    }

    public function hasBounds(float $min, float $max): bool
    {
        return $this->min === $min && $this->max === $max;
    }

    public function contains(self $other): bool
    {
        $minOk = $other->min > $this->min || ($other->min === $this->min && ($this->includeMin || ! $other->includeMin));
        $maxOk = $other->max < $this->max || ($other->max === $this->max && ($this->includeMax || ! $other->includeMax));

        return $minOk && $maxOk;
    }

    public function minBound(): ?float
    {
        return $this->min === -INF ? null : $this->min;
    }

    public function maxBound(): ?float
    {
        return $this->max === INF ? null : $this->max;
    }

    public function label(string $unit = ''): string
    {
        return match (true) {
            $this->min === -INF => 'poniżej '.$this->format($this->max).$unit,
            $this->max === INF => 'powyżej '.$this->format($this->min).$unit,
            $this->min === $this->max => $this->format($this->min).$unit,
            default => $this->format($this->min).'–'.$this->format($this->max).$unit,
        };
    }

    private function format(float $value): string
    {
        return str_replace('.', ',', (string) $value);
    }

    public function compare(float $reading): int
    {
        return match (true) {
            $reading < $this->min, $reading === $this->min && ! $this->includeMin => -1,
            $reading > $this->max, $reading === $this->max && ! $this->includeMax => 1,
            default => 0,
        };
    }

    public function intersect(self $other): ?self
    {
        $min = max($this->min, $other->min);
        $max = min($this->max, $other->max);
        $includeMin = $this->compare($min) === 0 && $other->compare($min) === 0;
        $includeMax = $this->compare($max) === 0 && $other->compare($max) === 0;

        return $min < $max || ($min === $max && $includeMin && $includeMax)
            ? new self($min, $max, includeMin: $includeMin, includeMax: $includeMax)
            : null;
    }
}

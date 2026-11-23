<?php

namespace App\Domain\Advisor\Engine\WaterHints;

/** Zweryfikowany zakres parametru z danych redakcji (niezależny od frameworka i modeli bazy wiedzy). */
final readonly class SpecRange
{
    public function __construct(
        public float $min,
        public float $max,
        public string $sourceKind,
        public string $sourceLabel,
        public ?string $sourceRef,
        public string $evidenceQuote,
    ) {}

    public function sourceDescription(): string
    {
        return $this->sourceRef !== null && $this->sourceRef !== ''
            ? "{$this->sourceLabel} ({$this->sourceRef})"
            : $this->sourceLabel;
    }
}

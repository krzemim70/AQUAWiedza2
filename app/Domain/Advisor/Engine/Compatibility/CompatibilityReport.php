<?php

namespace App\Domain\Advisor\Engine\Compatibility;

/** Wynik kontroli: ustalenia, tabela kontroli i stan. `assessed` = false, gdy zbiornik nie ma pasma wielkości. */
final readonly class CompatibilityReport
{
    /**
     * @param  list<Finding>  $findings
     * @param  list<CheckRow>  $rows
     * @param  int  $unknown  pozycje obsady spoza aktywnego katalogu (pominięte)
     */
    public function __construct(public bool $assessed, public array $findings, public array $rows, public int $unknown = 0) {}

    public function verdict(): Verdict
    {
        $severities = array_map(fn (Finding $f) => $f->severity, $this->findings);

        return match (true) {
            in_array(Severity::Conflict, $severities, true) => Verdict::Conflicts,
            in_array(Severity::Warning, $severities, true) => Verdict::Notes,
            default => Verdict::Clear,
        };
    }

    public function count(Severity $severity): int
    {
        return count(array_filter($this->findings, fn (Finding $f) => $f->severity === $severity));
    }

    /** Liczba ustaleń danej kontroli. */
    public function findingsFor(Check $check): int
    {
        return count(array_filter($this->findings, fn (Finding $f) => $f->check === $check));
    }

    /** @param list<string> $keys kody ustaleń przyjętych świadomie */
    public function without(array $keys): self
    {
        return new self($this->assessed, array_values(array_filter($this->findings, fn (Finding $f) => ! in_array($f->key(), $keys, true))), $this->rows, $this->unknown);
    }

    public function withUnknown(int $unknown): self
    {
        return new self($this->assessed, $this->findings, $this->rows, $unknown);
    }
}

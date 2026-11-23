<?php

namespace App\Domain\Aquarium;

use App\Domain\Advisor\Engine\Compatibility\CompatibilityReport;
use RuntimeException;

/** Dodanie gatunku ma ustalenia zgodności: wymaga świadomego potwierdzenia (`acknowledge: true`). */
class LivestockFitWarning extends RuntimeException
{
    public function __construct(public readonly CompatibilityReport $report)
    {
        parent::__construct('Baza wiedzy wskazuje uwagi dla tego gatunku w tym zbiorniku.');
    }
}

<?php

namespace App\Domain\Advisor\Engine\Compatibility;

use App\Domain\Advisor\Engine\GuideArea;

/** Pozycja obsady (rekord katalogu fauny albo flory). Ilość nie wpływa na reguły. */
final readonly class StockLine
{
    public function __construct(public GuideArea $area, public string $id, public int $quantity = 1) {}
}

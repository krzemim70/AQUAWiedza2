<?php

namespace App\Domain\Advisor\Engine\Compatibility;

/** Wiersz tabeli kontroli: ile sprawdzono (są dane) i ile bez danych. */
final readonly class CheckRow
{
    public function __construct(public Check $check, public int $checked, public int $noData) {}
}

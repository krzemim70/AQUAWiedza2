<?php

namespace App\Domain\Advisor\Engine;

/** Odwołanie do rekordu bez oceny (np. produkt współistniejący). */
final readonly class Ref
{
    public function __construct(public GuideArea $area, public string $id, public string $name) {}
}

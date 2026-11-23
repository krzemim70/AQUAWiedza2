<?php

namespace App\Domain\Advisor\Data;

/** Dane wejściowe do publicznego porównania gatunków „Czy te ryby mogą być razem?”. */
final readonly class CompareSpeciesData
{
    /** @param list<string> $ids */
    public function __construct(
        public array $ids,
        public ?string $search = null,
    ) {}
}

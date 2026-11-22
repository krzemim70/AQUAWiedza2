<?php

namespace App\Domain\Advisor\Engine;

/** Rekord z oceną (wewnętrzny). `backend`: wynik dogrywki technicznej [dopasowanie litrażu, dopasowanie typu]. */
final readonly class Scored
{
    /**
     * @param  array<string, int>  $scores
     * @param  list<string>  $reasons
     * @param  list<string>  $warnings
     * @param  array{int, int}  $backend
     */
    public function __construct(
        public Item $item,
        public int $total,
        public int $ideal,
        public int $weak,
        public array $scores,
        public array $reasons,
        public array $warnings,
        public int $percent,
        public array $backend,
    ) {}
}

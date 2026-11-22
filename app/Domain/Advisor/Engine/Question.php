<?php

namespace App\Domain\Advisor\Engine;

/** Następne pytanie. `search` = wyszukanie rekordu (bez opcji, odpowiedź to `obszar:ID`). */
final readonly class Question
{
    /** @param list<Option> $options */
    public function __construct(
        public string $key,
        public string $text,
        public array $options,
        public int $progress,
        public ?string $context = null,
        public ?string $intro = null,
        public bool $search = false,
    ) {}
}

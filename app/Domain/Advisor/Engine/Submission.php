<?php

namespace App\Domain\Advisor\Engine;

/** Wynik złożenia odpowiedzi: przy odrzuceniu lista odpowiedzi zostaje bez zmian. */
final readonly class Submission
{
    /** @param list<Answer> $answers */
    public function __construct(
        public bool $accepted,
        public ?string $message,
        public array $answers,
        public Question|Result|Verification $step,
    ) {}
}

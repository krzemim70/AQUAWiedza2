<?php

namespace App\Domain\Advisor;

use App\Domain\Advisor\Engine\Answer;
use App\Domain\Advisor\Engine\Question;
use App\Domain\Advisor\Engine\Result;
use App\Domain\Advisor\Engine\Verification;

/**
 * Stan kreatora po jednym kroku: partia katalogu, na której liczono (do zapisania razem z odpowiedziami), lista odpowiedzi,
 * następne pytanie albo wynik oraz komunikat odrzucenia ostatniej odpowiedzi (wtedy lista odpowiedzi jest bez zmian).
 */
final readonly class GuidedStep
{
    /** @param list<Answer> $answers */
    public function __construct(
        public ?int $batch,
        public array $answers,
        public Question|Result|Verification $step,
        public ?string $rejection = null,
    ) {}
}

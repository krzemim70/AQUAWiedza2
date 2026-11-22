<?php

namespace App\Domain\Advisor\Actions;

use App\Domain\Advisor\Engine\Answer;
use App\Domain\Advisor\Engine\InvalidAnswers;
use App\Domain\Advisor\GuidedStep;
use App\Domain\Advisor\GuideSource;

/**
 * Kreator doradcy: lista odpowiedzi (+ opcjonalnie nowa odpowiedź) → następne pytanie albo wynik.
 * Pierwsze wywołanie bez partii przypina aktywną; kolejne powinny podawać zapisaną partię, żeby wynik dał się odtworzyć.
 * Cofnięcie to wywołanie bez ostatniej odpowiedzi.
 */
class RunGuidedAdvisor
{
    public function __construct(private GuideSource $source) {}

    /**
     * @param  list<Answer>  $answers
     *
     * @throws InvalidAnswers gdy odpowiedzi nie odtwarzają się na wskazanej partii
     */
    public function handle(array $answers = [], ?int $batch = null, ?Answer $answer = null): GuidedStep
    {
        $batch = $this->source->batchId($batch);
        $guide = $this->source->guide($batch);

        if ($answer === null) {
            [$step, $questions] = $guide->review($answers);

            return new GuidedStep($batch, $answers, $step, questions: $questions);
        }
        $submission = $guide->submit($answers, $answer);

        return new GuidedStep($batch, $submission->answers, $submission->step, $submission->accepted ? null : $submission->message);
    }
}

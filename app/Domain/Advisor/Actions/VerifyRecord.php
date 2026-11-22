<?php

namespace App\Domain\Advisor\Actions;

use App\Domain\Advisor\Engine\Answer;
use App\Domain\Advisor\GuidedStep;
use App\Domain\Advisor\GuideSource;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;

/** Weryfikacja wskazanego rekordu (pytania o warunki albo werdykt). Null, gdy rekordu nie ma w partii. */
class VerifyRecord
{
    public function __construct(private GuideSource $source, private Catalog $catalog) {}

    /** @param list<Answer> $answers odpowiedzi na pytania weryfikacji (klucze `verify.*`) */
    public function handle(Area $area, string $id, array $answers = [], ?int $batch = null): ?GuidedStep
    {
        $batch = $this->source->batchId($batch);
        $entry = $this->catalog->find($area, $id, $batch);

        return $entry === null ? null : new GuidedStep($batch, $answers, $this->source->guide($batch)->verify(GuideSource::item($entry), $answers));
    }
}

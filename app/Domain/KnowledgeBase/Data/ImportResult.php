<?php

namespace App\Domain\KnowledgeBase\Data;

use App\Domain\KnowledgeBase\Models\Batch;

final readonly class ImportResult
{
    public const IMPORTED = 'imported';

    public const UNCHANGED = 'unchanged';

    public const FAILED = 'failed';

    /** @param list<string> $errors */
    public function __construct(
        public string $status,
        public ?Batch $batch,
        public array $errors = [],
    ) {}
}

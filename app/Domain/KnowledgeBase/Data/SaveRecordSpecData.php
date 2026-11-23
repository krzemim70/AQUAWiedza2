<?php

namespace App\Domain\KnowledgeBase\Data;

use App\Domain\KnowledgeBase\Models\SpecSourceKind;

final readonly class SaveRecordSpecData
{
    /**
     * @param  array<string, mixed>  $attributes
     */
    public function __construct(
        public int $recordId,
        public SpecSourceKind $sourceKind,
        public ?string $sourceRef,
        public string $evidenceQuote,
        public ?string $ean,
        public array $attributes,
    ) {}
}

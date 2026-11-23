<?php

namespace App\Domain\Advisor\Engine\WaterHints;

use App\Domain\Advisor\Engine\Evidence;

final readonly class Hint
{
    /** @param list<Evidence> $evidence */
    public function __construct(
        public string $message,
        public array $evidence = [],
        public bool $reviewStock = false,
        public ?PhProductCheck $phProduct = null,
        public string $parameter = 'ph',
        public ?string $productDirection = null,
        public ?string $productBlockReason = null,
    ) {}
}

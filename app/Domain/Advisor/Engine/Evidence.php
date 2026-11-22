<?php

namespace App\Domain\Advisor\Engine;

/** Dowód porady (zasada „żadnych porad bez podstawy”): kolumna i wartość z rekordu bazy wiedzy, ewentualnie numer partii. */
final readonly class Evidence
{
    public function __construct(
        public string $column,
        public string $value,
        public string $recordId,
        public ?int $batch = null,
    ) {}

    public function text(): string
    {
        return "baza: {$this->column} = {$this->value} ({$this->recordId})";
    }
}

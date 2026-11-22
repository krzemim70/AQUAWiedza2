<?php

namespace App\Domain\Aquarium\Data;

final readonly class JournalEntryData
{
    public function __construct(public string $entryDate, public string $content) {}
}

<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Advisor\Engine\Evidence;
use App\Domain\Aquarium\Models\ReminderType;

/** Podpowiedź rodzaju przypomnienia: tylko z rekordów bazy wiedzy, które same podają czynność (cytat jako dowód). */
final readonly class ReminderHint
{
    /** @param list<array{name: string, evidence: Evidence}> $sources rekordy zbiornika, z których wynika podpowiedź */
    public function __construct(public ReminderType $type, public array $sources) {}
}

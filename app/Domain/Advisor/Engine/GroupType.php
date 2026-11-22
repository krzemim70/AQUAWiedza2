<?php

namespace App\Domain\Advisor\Engine;

/** Typ grupy wyniku. */
enum GroupType: string
{
    case Leader = 'leader';
    case Tiebreak = 'tiebreak';
    case PartialTie = 'partial_tie';
    case FullTie = 'full_tie';
    case Empty = 'empty';
}

<?php

namespace App\Domain\KnowledgeBase\Models;

enum RecordSpecStatus: string
{
    case Draft = 'draft';
    case Verified = 'verified';
    case Disabled = 'disabled';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Do weryfikacji',
            self::Verified => 'Zweryfikowane',
            self::Disabled => 'Wyłączone po imporcie',
        };
    }
}

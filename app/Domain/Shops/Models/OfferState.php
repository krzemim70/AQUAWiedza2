<?php

namespace App\Domain\Shops\Models;

enum OfferState: string
{
    case Draft = 'draft';
    case Published = 'published';
    case Expired = 'expired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Szkic',
            self::Published => 'Opublikowana',
            self::Expired => 'Wygasła',
            self::Withdrawn => 'Wycofana',
        };
    }
}

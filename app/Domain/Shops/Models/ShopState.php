<?php

namespace App\Domain\Shops\Models;

enum ShopState: string
{
    case Verification = 'verification';
    case Preparation = 'preparation';
    case Active = 'active';
    case Suspended = 'suspended';
    case Ended = 'ended';

    public function label(): string
    {
        return match ($this) {
            self::Verification => 'W weryfikacji',
            self::Preparation => 'W przygotowaniu',
            self::Active => 'Aktywny',
            self::Suspended => 'Wstrzymany',
            self::Ended => 'Zakończony',
        };
    }
}

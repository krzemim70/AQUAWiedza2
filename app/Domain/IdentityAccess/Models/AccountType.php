<?php

namespace App\Domain\IdentityAccess\Models;

enum AccountType: string
{
    case Aquarist = 'aquarist';
    case Shop = 'shop';

    public function label(): string
    {
        return match ($this) {
            self::Aquarist => 'Akwarysta',
            self::Shop => 'Sklep',
        };
    }
}

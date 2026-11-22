<?php

namespace App\Domain\IdentityAccess\Models;

enum AccessChangeKind: string
{
    case Activated = 'activated';
    case FishroomGranted = 'fishroom_granted';
    case FishroomRevoked = 'fishroom_revoked';
    case FishroomExpired = 'fishroom_expired';
    case Suspended = 'suspended';
    case Unsuspended = 'unsuspended';

    public function label(): string
    {
        return match ($this) {
            self::Activated => 'Aktywowano konto',
            self::FishroomGranted => 'Nadano Fishroom',
            self::FishroomRevoked => 'Przywrócono Tank',
            self::FishroomExpired => 'Fishroom wygasł (Tank)',
            self::Suspended => 'Zawieszono konto',
            self::Unsuspended => 'Odwieszono konto',
        };
    }
}

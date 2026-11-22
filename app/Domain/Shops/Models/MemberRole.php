<?php

namespace App\Domain\Shops\Models;

enum MemberRole: string
{
    case Owner = 'owner';
    case Staff = 'staff';

    public function label(): string
    {
        return match ($this) {
            self::Owner => 'Właściciel',
            self::Staff => 'Pracownik',
        };
    }
}

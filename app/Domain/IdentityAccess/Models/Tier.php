<?php

namespace App\Domain\IdentityAccess\Models;

enum Tier: string
{
    case Drop = 'drop';
    case Tank = 'tank';
    case Fishroom = 'fishroom';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}

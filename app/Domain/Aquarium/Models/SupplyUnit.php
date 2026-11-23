<?php

namespace App\Domain\Aquarium\Models;

enum SupplyUnit: string
{
    case Ml = 'ml';
    case Gram = 'g';
    case Piece = 'szt';
    case Drop = 'krople';

    public function label(): string
    {
        return match ($this) {
            self::Ml => 'ml',
            self::Gram => 'g',
            self::Piece => 'szt.',
            self::Drop => 'krople',
        };
    }
}

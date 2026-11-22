<?php

namespace App\Domain\Advisor\Engine;

/** Kryteria oceny w kolejności ważności. */
enum Criterion: string
{
    case Size = 'size';
    case Fish = 'fish';
    case Temperature = 'temperature';
    case Ph = 'ph';

    public function label(): string
    {
        return match ($this) {
            self::Size => 'wielkość akwarium',
            self::Fish => 'wielkość ryby',
            self::Temperature => 'temperatura',
            self::Ph => 'pH',
        };
    }
}

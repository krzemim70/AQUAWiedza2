<?php

namespace App\Domain\Advisor\Engine;

/** Wspólne dla słowników pasm: kolejność od najmniejszego/najniższego oraz zamiana etykiety z katalogu na pasmo. */
trait OrderedBand
{
    public function index(): int
    {
        return (int) array_search($this, self::cases(), true);
    }

    public static function fromLabel(?string $label): ?self
    {
        $label = trim((string) $label);
        foreach (self::cases() as $case) {
            if ($case->label() === $label) {
                return $case;
            }
        }

        return null;
    }
}

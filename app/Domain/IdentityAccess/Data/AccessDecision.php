<?php

namespace App\Domain\IdentityAccess\Data;

use App\Domain\IdentityAccess\Models\Tier;

/** Wynik ResolveAccess; message to jedyny tekst odmowy pokazywany użytkownikowi. */
final readonly class AccessDecision
{
    public function __construct(
        public bool $allowed,
        public Tier $tier,
        public string $reason,
        public ?string $message = null,
    ) {}
}

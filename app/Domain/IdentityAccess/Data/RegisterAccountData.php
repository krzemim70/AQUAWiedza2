<?php

namespace App\Domain\IdentityAccess\Data;

use App\Domain\IdentityAccess\Models\AccountType;

/** Pola sklepu (companyName…phone) wymagane tylko dla typu Shop. */
final readonly class RegisterAccountData
{
    public function __construct(
        public AccountType $type,
        public string $name,
        public string $email,
        public string $password,
        public bool $newsletter = false,
        public ?string $ip = null,
        public ?string $companyName = null,
        public ?string $nip = null,
        public ?string $address = null,
        public ?string $phone = null,
    ) {}
}

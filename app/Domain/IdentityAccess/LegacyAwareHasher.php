<?php

namespace App\Domain\IdentityAccess;

use Illuminate\Hashing\BcryptHasher;

/**
 * Bcrypt, który dodatkowo przyjmuje skróty z WordPressa. `needsRehash` zwraca dla nich true,
 * więc po udanym logowaniu Laravel zapisuje zwykły bcrypt.
 */
class LegacyAwareHasher extends BcryptHasher
{
    /** @param array<string, mixed> $options */
    public function check(#[\SensitiveParameter] $value, $hashedValue, array $options = []): bool
    {
        if ($value === '' || ! is_string($hashedValue)) {
            return false;
        }

        return WordPressPassword::isLegacy($hashedValue)
            ? WordPressPassword::check($value, $hashedValue)
            : parent::check($value, $hashedValue, $options);
    }
}

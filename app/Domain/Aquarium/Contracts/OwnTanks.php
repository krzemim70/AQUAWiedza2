<?php

namespace App\Domain\Aquarium\Contracts;

use App\Domain\Aquarium\Models\Tank;
use App\Models\User;

class OwnTanks
{
    /** @return array<int, string> nazwy zbiorników po ich ID */
    public function names(User $owner): array
    {
        return Tank::ownedBy($owner)->orderBy('id')->pluck('name', 'id')->all();
    }
}

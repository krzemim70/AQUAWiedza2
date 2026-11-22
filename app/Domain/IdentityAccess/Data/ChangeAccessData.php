<?php

namespace App\Domain\IdentityAccess\Data;

use App\Domain\IdentityAccess\Models\AccessChangeKind;
use App\Models\User;
use Carbon\CarbonInterface;

final readonly class ChangeAccessData
{
    public function __construct(
        public User $actor,
        public User $user,
        public AccessChangeKind $kind,
        public string $reason,
        public ?CarbonInterface $fishroomEndsAt = null,
    ) {}
}

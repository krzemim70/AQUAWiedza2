<?php

namespace App\Domain\Settings;

use App\Models\User;
use Carbon\CarbonInterface;

final readonly class ChangeSettingData
{
    public function __construct(
        public User $actor,
        public SettingKey $key,
        public mixed $value,
        public string $reason,
        public ?CarbonInterface $effectiveFrom = null,
    ) {}
}

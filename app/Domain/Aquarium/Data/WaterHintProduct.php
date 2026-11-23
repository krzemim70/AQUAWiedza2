<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\Advisor\Engine\Evidence;

final readonly class WaterHintProduct
{
    public function __construct(public string $name, public Evidence $evidence) {}
}

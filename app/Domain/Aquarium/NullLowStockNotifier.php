<?php

namespace App\Domain\Aquarium;

use App\Domain\Aquarium\Contracts\LowStockNotifier;
use Carbon\CarbonInterface;

class NullLowStockNotifier implements LowStockNotifier
{
    public function handle(CarbonInterface $localNow): int
    {
        return 0;
    }
}

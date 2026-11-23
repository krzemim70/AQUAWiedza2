<?php

namespace App\Domain\Aquarium\Contracts;

use Carbon\CarbonInterface;

/**
 * Punkt rozszerzenia dla pakietu wirtualnych zapasów (`tank_supplies`).
 * Wywoływany przez SendDueReminders raz dziennie po godzinie z ustawień.
 */
interface LowStockNotifier
{
    /** Zwraca liczbę wysłanych powiadomień o niskim stanie zapasów. */
    public function handle(CarbonInterface $localNow): int;
}

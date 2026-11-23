<?php

namespace App\Domain\Aquarium\Models;

enum SupplyEventSource: string
{
    case Manual = 'manual';
    case Device = 'device';
}

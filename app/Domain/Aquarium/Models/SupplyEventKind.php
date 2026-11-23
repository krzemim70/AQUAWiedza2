<?php

namespace App\Domain\Aquarium\Models;

enum SupplyEventKind: string
{
    case Use = 'use';
    case Restock = 'restock';
}

<?php

namespace App\Domain\Shops\Data;

final readonly class ShopRating
{
    public function __construct(public ?float $average = null, public int $count = 0) {}
}

<?php

namespace App\Domain\Shops\Data;

final readonly class ReviewData
{
    public function __construct(public int $rating, public ?string $comment = null) {}
}

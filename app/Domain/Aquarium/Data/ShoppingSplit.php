<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\KnowledgeBase\Models\Area;

/** Wynik podziału listy zakupów po placówkach oraz lista pozycji bez dostępnych ofert. */
final readonly class ShoppingSplit
{
    /**
     * @param  list<ShoppingLocationGroup>  $groups
     * @param  list<array{area: Area, id: string, name: string, wanted: int}>  $missing
     */
    public function __construct(
        public array $groups,
        public array $missing,
        public ?string $city,
    ) {}
}

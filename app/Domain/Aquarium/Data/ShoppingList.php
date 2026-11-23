<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Data\PublicOffer;

/**
 * Lista zakupów planu: oferty z okolicy pogrupowane po placówce sklepu i pozycje bez ofert.
 * Miejsce na przyszły optymalizator: ten sam typ wyniku, inna reguła grupowania.
 */
final readonly class ShoppingList
{
    /**
     * @param  list<array{shop: string, location: string, address: string, city: string, lines: list<array{area: Area, id: string, name: string, wanted: int, offerId: int, variant: string, freeQty: int, priceGrosze: int, offer: PublicOffer}>}>  $groups
     * @param  list<array{area: Area, id: string, name: string, wanted: int}>  $missing  pozycje bez ofert w okolicy
     * @param  string|null  $city  miasto użytkownika użyte do filtra (null = bez filtra, wszystkie oferty)
     */
    public function __construct(public array $groups, public array $missing, public ?string $city) {}
}

<?php

namespace App\Domain\Aquarium\Data;

use App\Domain\KnowledgeBase\Models\Area;

/** Pozycja planu tworzonego w kreatorze (wszystkie cztery obszary katalogu). */
final readonly class CreatorLine
{
    public function __construct(public Area $area, public string $catalogId, public int $quantity) {}
}

<?php

namespace App\Domain\Advisor\Engine;

/** Rekordy czterech obszarów (w kolejności pliku) i słowniki wyprowadzane z całego katalogu. */
final class Records
{
    /** @var array<string, list<Item>> */
    private array $byArea = [];

    /** @param list<Item> $items */
    public function __construct(array $items)
    {
        foreach ($items as $item) {
            $this->byArea[$item->area->value][] = $item;
        }
    }

    /** @return list<Item> */
    public function of(GuideArea $area): array
    {
        return $this->byArea[$area->value] ?? [];
    }

    public function find(GuideArea $area, string $id): ?Item
    {
        return array_find($this->of($area), fn (Item $i) => $i->id === $id);
    }

    /** Kod odpowiedzi `obszar:ID`. */
    public function fromCode(string $code): ?Item
    {
        [$area, $id] = array_pad(explode(':', $code, 2), 2, '');
        $area = GuideArea::tryFrom($area);

        return $area === null ? null : $this->find($area, $id);
    }

    /**
     * Różne niepuste wartości kolumny (po rozbiciu `;`) w naturalnej kolejności.
     *
     * @param  list<Item>  $items
     * @return list<string>
     */
    public static function distinct(array $items, string $column): array
    {
        $values = array_unique(array_merge([], ...array_map(fn (Item $i) => $i->tokens($column), $items)));
        sort($values, SORT_NATURAL | SORT_FLAG_CASE);

        return $values;
    }

    /**
     * Litraże dla pytania kwalifikacyjnego: unia fauny, flory i sprzętu bez technicznego „Wszystkie”, pasma w kolejności.
     *
     * @return list<string>
     */
    public function tankSizes(): array
    {
        $all = [...$this->of(GuideArea::Fauna), ...$this->of(GuideArea::Flora), ...$this->of(GuideArea::Equipment)];
        $sizes = array_diff(self::distinct($all, 'WielkośćAkwarium'), [Vocabulary::WILDCARD]);

        return Vocabulary::order($sizes, array_map(fn (TankBand $b) => $b->label(), TankBand::cases()));
    }

    /**
     * Typy akwarium z podanych obszarów w kolejności: Nano, Ogólne, Roślinne, Biotop, potem pozostałe.
     *
     * @param  list<GuideArea>  $areas
     * @return list<string>
     */
    public function types(array $areas): array
    {
        $items = array_merge([], ...array_map($this->of(...), $areas));

        return Vocabulary::order(self::distinct($items, 'TypAkwarium'), [Vocabulary::NANO_TYPE, 'Ogólne / Towarzyskie', 'Roślinne', 'Biotop Twardowodny (Malawi/Tanganika)']);
    }
}

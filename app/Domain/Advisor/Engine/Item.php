<?php

namespace App\Domain\Advisor\Engine;

/** Rekord katalogu dla kreatora: surowe pola po nagłówku kolumny i (opcjonalnie) te same kolumny rozbite po `;`. */
final readonly class Item
{
    /**
     * @param  array<string, string>  $fields
     * @param  array<string, list<string>>  $values
     */
    public function __construct(
        public GuideArea $area,
        public string $id,
        public string $name,
        public ?string $latin,
        public array $fields,
        public array $values = [],
    ) {}

    public function has(string $column): bool
    {
        return array_key_exists($column, $this->fields);
    }

    public function raw(string $column): string
    {
        return trim($this->fields[$column] ?? '');
    }

    /** @return list<string> */
    public function tokens(string $column): array
    {
        return $this->values[$column]
            ?? array_values(array_filter(array_map('trim', explode(';', $this->raw($column))), fn (string $t) => $t !== ''));
    }
}

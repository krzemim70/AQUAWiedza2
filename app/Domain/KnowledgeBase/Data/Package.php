<?php

namespace App\Domain\KnowledgeBase\Data;

/** Odczytana i sprawdzona paczka plików katalogu. Przy niepustym `errors` nie nadaje się do zapisu. */
final readonly class Package
{
    /**
     * @param  array<string, array{sha256: string, rows: int|null}>  $files  po nazwie pliku
     * @param  array<string, list<array<string, string>>>  $rows  wiersze TSV po wartości obszaru (kolejność pliku)
     * @param  array<string, array<string, array<string, string>>>  $descriptions  obszar → id → pola z pliku opisów
     * @param  list<array{area: string, id: string, status: string, source_status: string, name: string|null}>  $registry
     * @param  list<string>  $errors
     */
    public function __construct(
        public array $files,
        public ?string $checksum,
        public array $rows,
        public array $descriptions,
        public array $registry,
        public array $errors,
    ) {}

    public function ok(): bool
    {
        return $this->errors === [];
    }

    /** @return array<string, int> */
    public function counts(): array
    {
        return array_map('count', $this->rows);
    }
}

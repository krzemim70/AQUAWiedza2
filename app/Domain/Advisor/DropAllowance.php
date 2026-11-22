<?php

namespace App\Domain\Advisor;

/**
 * Wykorzystanie bezpłatnych doborów gościa w oknie czasowym: identyfikator przeglądarki, początek okna
 * i klucze żądań, które odsłoniły kartę. Powtórzenie zużytego klucza nie zużywa kolejnego doboru.
 */
final readonly class DropAllowance
{
    /** @param list<string> $keys */
    public function __construct(
        public string $id,
        public int $startedAt,
        public array $keys = [],
    ) {}

    /** Brakujące, uszkodzone lub wygasłe okno zaczyna się od nowa. */
    public static function fromCookie(?string $cookie, int $now, int $windowSeconds): self
    {
        $data = json_decode((string) $cookie, true);
        $valid = is_array($data) && is_string($data['id'] ?? null) && is_int($data['started_at'] ?? null)
            && is_array($data['keys'] ?? null) && $now < $data['started_at'] + $windowSeconds;

        return $valid
            ? new self($data['id'], $data['started_at'], array_values(array_filter($data['keys'], is_string(...))))
            : new self(bin2hex(random_bytes(8)), $now);
    }

    /** @param list<string> $usedKeys */
    public static function permits(array $usedKeys, string $key, int $limit): bool
    {
        return in_array($key, $usedKeys, true) || count($usedKeys) < $limit;
    }

    public function with(string $key): self
    {
        return in_array($key, $this->keys, true) ? $this : new self($this->id, $this->startedAt, [...$this->keys, $key]);
    }

    public function toCookie(): string
    {
        return json_encode(['id' => $this->id, 'started_at' => $this->startedAt, 'keys' => $this->keys], JSON_THROW_ON_ERROR);
    }
}

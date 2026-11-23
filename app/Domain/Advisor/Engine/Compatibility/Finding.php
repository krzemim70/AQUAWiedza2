<?php

namespace App\Domain\Advisor\Engine\Compatibility;

use App\Domain\Advisor\Engine\Evidence;

/** Ustalenie kontroli: ważność, tekst i dowód z rekordów bazy wiedzy. */
final readonly class Finding
{
    /**
     * @param  list<string>  $ids  rekordy, których dotyczy
     * @param  list<Evidence>  $evidence
     */
    public function __construct(
        public Check $check,
        public Severity $severity,
        public array $ids,
        public string $text,
        public array $evidence,
    ) {}

    /** Stabilny kod ustalenia (do zapisu przyjęcia): kontrola i posortowane identyfikatory. */
    public function key(): string
    {
        $ids = $this->ids;
        sort($ids);

        return $this->check->value.':'.implode(':', $ids);
    }
}

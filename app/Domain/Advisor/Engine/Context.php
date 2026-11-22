<?php

namespace App\Domain\Advisor\Engine;

/** Stan odtworzony z listy odpowiedzi (wewnętrzny). `answers`: kolumna katalogu => wybrana etykieta. */
final class Context
{
    public ?GuideArea $area = null;

    public bool $find = false;

    public ?Item $record = null;

    public ?string $volume = null;

    public ?string $type = null;

    /** @var array<string, string> */
    public array $answers = [];
}

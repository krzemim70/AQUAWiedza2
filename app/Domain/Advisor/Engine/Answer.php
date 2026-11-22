<?php

namespace App\Domain\Advisor\Engine;

/** Odpowiedź na pytanie: klucz pytania i stabilny kod opcji (przy wyszukaniu produktu: `obszar:ID`). */
final readonly class Answer
{
    public function __construct(public string $key, public string $code) {}
}

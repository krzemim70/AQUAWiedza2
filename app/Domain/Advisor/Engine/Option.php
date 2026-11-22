<?php

namespace App\Domain\Advisor\Engine;

final readonly class Option
{
    public function __construct(public string $code, public string $label) {}
}

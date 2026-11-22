<?php

namespace App\Domain\Advisor\Engine;

/** Weryfikacja wybranego rekordu względem warunków użytkownika. */
final readonly class Verification
{
    /**
     * @param  list<array{label: string, user: string, optimal: string, status: string}>  $compatibility
     * @param  list<string>  $reasons
     * @param  list<string>  $warnings
     * @param  list<Ref>  $supporting  produkty współistniejące (pokarmy, nawozy)
     */
    public function __construct(
        public GuideArea $area,
        public string $id,
        public string $name,
        public int $percent,
        public string $verdict,
        public array $compatibility,
        public array $reasons,
        public array $warnings,
        public ?Card $alternative,
        public ?Card $correction,
        public array $supporting,
        public ?string $notice,
    ) {}
}

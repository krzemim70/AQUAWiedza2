<?php

namespace App\Http;

final class AdvisorReturn
{
    public static function results(mixed $value): ?string
    {
        return self::local($value, '/doradca(?:/[A-Za-z0-9_-]+)*/?');
    }

    public static function card(mixed $value): ?string
    {
        return self::results($value) ?? self::local($value, '/katalog/[a-z]+/[A-Za-z0-9_-]+');
    }

    private static function local(mixed $value, string $path): ?string
    {
        if (! is_string($value) || str_contains($value, '\\') || preg_match('/[\x00-\x20\x7f]/', $value)) {
            return null;
        }

        return preg_match('~\A'.$path.'(?:[?#].*)?\z~', $value) === 1 ? $value : null;
    }
}

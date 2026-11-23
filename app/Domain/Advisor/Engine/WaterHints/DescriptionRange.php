<?php

namespace App\Domain\Advisor\Engine\WaterHints;

/** Rozpoznaje pojedynczy jednoznaczny przedział parametru (pH lub temperatura) w opisie tekstowym rekordu. */
final class DescriptionRange
{
    /**
     * shortcut: rozpoznajemy tylko jeden jednoznaczny przedział przy nazwie parametru; inne zapisy wymagają danych strukturalnych.
     *
     * @return array{string, float, float}|null
     */
    public static function extract(?string $description, bool $ph, bool $forProduct = false): ?array
    {
        if ($description === null) {
            return null;
        }

        $parameter = $ph ? 'pH' : 'temperatura';
        $unit = match (true) {
            $forProduct => '(?![\d\p{L}]|[,.]\d|\h*(?:[°%/–—-]|(?:lub|do)\h+\d))',
            $ph => '(?!\d|[,.]\d|\h*[°%])',
            default => '\h*°\h*C\b',
        };
        $number = '(-?\d+(?:[,.]\d+)?)';
        $qualifier = $forProduct ? 'około|mniej\h+więcej' : 'około';
        preg_match_all("~\b$parameter\h*(?:[:=]\h*)?(?:(?:$qualifier)\h+)?$number\h*[-–—]\h*$number$unit~iu", $description, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE);
        $bounds = [];
        foreach ($matches as [[$excerpt, $offset], [$low], [$high]]) {
            $min = (float) str_replace(',', '.', $low);
            $max = (float) str_replace(',', '.', $high);
            if ($min > $max || ($ph && ($min < 0 || $max > 14))) {
                return null;
            }
            if ($forProduct && self::negatesRange($description, $excerpt, $offset)) {
                return null;
            }
            $bounds["$min:$max"] = [$excerpt, $min, $max];
        }

        return count($bounds) === 1 ? array_values($bounds)[0] : null;
    }

    private static function negatesRange(string $description, string $excerpt, int $offset): bool
    {
        [$before] = array_slice(preg_split('/[.!?]\s+/u', substr($description, 0, $offset)) ?: [''], -1);
        [$after] = preg_split('/[.!?]\s+/u', substr($description, $offset + strlen($excerpt)), 2) ?: [''];

        return preg_match('/\b(?:nie|niewskazan\p{L}*|unika\p{L}*|poza)\b/iu', "$before $after") === 1;
    }
}

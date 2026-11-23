<?php

namespace App\Domain\Shops\OfferImport;

use InvalidArgumentException;

final class Csv
{
    public const HEADERS = ['id_rekordu', 'wariant', 'cena', 'ilosc', 'placowka', 'wazna_od', 'wazna_do', 'publikuj'];

    public static function template(): string
    {
        return implode(';', self::HEADERS)."\nFN0001;Mały;15,00;5;;;;tak\nSP0046;;129,90;2;;;;tak\nCH0047;;27,50;0;;;;nie\n";
    }

    /**
     * @return array{encoding: string, separator: string, rows: array<int, array<string, string>|null>}
     */
    public function read(string $bytes, int $maxBytes, int $maxRows): array
    {
        if (strlen($bytes) > $maxBytes) {
            throw new InvalidArgumentException("Plik może mieć najwyżej $maxBytes bajtów.");
        }

        $encoding = 'UTF-8';
        if (str_starts_with($bytes, "\xEF\xBB\xBF")) {
            $bytes = substr($bytes, 3);
        } elseif (! mb_check_encoding($bytes, 'UTF-8')) {
            $encoding = 'Windows-1250';
            $converted = preg_match('/[\x81\x83\x88\x90\x98]/', $bytes) ? false : iconv($encoding, 'UTF-8', $bytes);
            if ($converted === false) {
                throw new InvalidArgumentException('Zapisz plik jako CSV w kodowaniu UTF-8 lub Windows-1250.');
            }
            $bytes = $converted;
        }
        if (! mb_check_encoding($bytes, 'UTF-8') || preg_match('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F-\x9F\x{FFFD}]/u', $bytes)) {
            throw new InvalidArgumentException('Zapisz plik jako CSV w kodowaniu UTF-8 lub Windows-1250.');
        }
        $bytes = str_replace(["\r\n", "\r"], "\n", $bytes);
        $header = strstr($bytes, "\n", true);
        $header = $header === false ? $bytes : $header;
        if ($header === '') {
            throw new InvalidArgumentException('Brak nagłówka CSV.');
        }

        foreach ([';', ','] as $separator) {
            $pattern = $this->recordPattern($separator);
            if (preg_match($pattern, $header) !== 1) {
                continue;
            }
            $columns = array_map(trim(...), str_getcsv($header, $separator, '"', ''));
            if (! array_diff(['id_rekordu', 'cena', 'ilosc'], $columns)
                && ! array_diff($columns, self::HEADERS) && count($columns) === count(array_unique($columns))) {
                return [
                    'encoding' => $encoding,
                    'separator' => $separator,
                    'rows' => $this->rows($bytes, strlen($header) + 1, $separator, $columns, $maxRows),
                ];
            }
        }

        throw new InvalidArgumentException('Nagłówek musi zawierać kolumny id_rekordu, cena i ilosc. Użyj nazw kolumn ze wzoru, bez powtórzeń.');
    }

    /**
     * @param  list<string>  $columns
     * @return array<int, array<string, string>|null>
     */
    private function rows(string $bytes, int $offset, string $separator, array $columns, int $maxRows): array
    {
        $line = 2;
        $rows = [];
        $size = strlen($bytes);
        $pattern = $this->recordPattern($separator);

        while ($offset < $size) {
            $valid = preg_match($pattern, $bytes, $match, offset: $offset) === 1;
            if ($valid) {
                $raw = $match[0];
            } else {
                // Niedomknięty cudzysłów nie powinien przesłonić dalszych wierszy.
                $lineBreak = strpos($bytes, "\n", $offset);
                $raw = substr($bytes, $offset, ($lineBreak === false ? $size : $lineBreak + 1) - $offset);
            }
            $offset += strlen($raw);
            $number = $line;
            $line += substr_count($raw, "\n");
            if (trim($raw) === '') {
                continue;
            }
            if (count($rows) >= $maxRows) {
                throw new InvalidArgumentException("Plik może zawierać najwyżej $maxRows wierszy danych.");
            }
            $values = $valid ? str_getcsv(rtrim($raw, "\n"), $separator, '"', '') : [];
            $rows[$number] = count($values) === count($columns) ? array_combine($columns, array_map(trim(...), $values)) : null;
        }

        if ($rows === []) {
            throw new InvalidArgumentException('Plik nie zawiera wierszy danych.');
        }

        return $rows;
    }

    private function recordPattern(string $separator): string
    {
        $field = '(?:"(?:[^"]++|"")*+"|[^"'.$separator.'\n]*+)';
        $additionalColumns = count(self::HEADERS) - 1;

        return '/\G'.$field.'(?:'.$separator.$field.'){0,'.$additionalColumns.'}+(?:\n|\z)/A';
    }
}

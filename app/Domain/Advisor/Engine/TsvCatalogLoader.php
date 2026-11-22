<?php

namespace App\Domain\Advisor\Engine;

final class TsvCatalogLoader
{
    /** @return list<array<string, string>> */
    public function load(string $path): array
    {
        $handle = @fopen($path, 'r');
        if ($handle === false) {
            return [];
        }

        try {
            $headers = fgetcsv($handle, 0, "\t", '"', '\\');
            if ($headers === false) {
                return [];
            }

            $clean = static fn (mixed $value): string => preg_replace('/^\xC3\xAF\xC2\xBB\xC2\xBF/', '', trim((string) $value)) ?? '';
            $headers = array_map($clean, $headers);
            $rows = [];
            while (($data = fgetcsv($handle, 0, "\t", '"', '\\')) !== false) {
                if (count($data) === 1 && trim((string) $data[0]) === '') {
                    continue;
                }
                $row = [];
                foreach ($headers as $index => $header) {
                    $row[$header] = $clean($data[$index] ?? '');
                }
                if (trim($row['id'] ?? '') !== '') {
                    $rows[] = $row;
                }
            }

            return $rows;
        } finally {
            fclose($handle);
        }
    }

    /** @return list<string> */
    public static function splitMulti(?string $value): array
    {
        if ($value === null || $value === '') {
            return [];
        }

        return array_values(array_filter(array_map('trim', explode(';', $value)), static fn (string $part): bool => $part !== ''));
    }
}

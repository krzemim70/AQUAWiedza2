<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Models\Photo;
use Illuminate\Support\Facades\Storage;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

/**
 * Kopiuje pliki zdjęć z lokalnej kopii `wp-content/uploads` na dysk `public` (katalog `kb/`) i przestawia adres zdjęcia
 * na lokalny. Dopasowanie po ścieżce względnej (`file_path` lub fragment URL po `/uploads/`), a gdy jej brak, po nazwie pliku.
 * Idempotentne: zdjęcie już wskazujące na `kb/` jest pomijane.
 */
class ImportPhotoFiles
{
    private const TARGET = 'kb/';

    /** @return array{found: int, missing: int, already: int} */
    public function handle(string $directory): array
    {
        $root = rtrim(str_replace(DIRECTORY_SEPARATOR, '/', $directory), '/');
        $byName = [];
        foreach (new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)) as $file) {
            $byName[basename($file->getPathname())] ??= str_replace(DIRECTORY_SEPARATOR, '/', $file->getPathname());
        }

        $disk = Storage::disk('public');
        $result = ['found' => 0, 'missing' => 0, 'already' => 0];

        foreach (Photo::orderBy('id')->get() as $photo) {
            if (str_starts_with((string) $photo->file_path, self::TARGET) && $disk->exists($photo->file_path)) {
                $result['already']++;

                continue;
            }

            $relative = $photo->file_path ?: (str_contains((string) $photo->url, '/uploads/') ? substr((string) $photo->url, strrpos((string) $photo->url, '/uploads/') + 9) : '');
            $relative = ltrim(str_replace(DIRECTORY_SEPARATOR, '/', $relative), '/');
            $name = basename($relative !== '' ? $relative : (string) parse_url((string) $photo->url, PHP_URL_PATH));
            $source = $relative !== '' && ! str_contains($relative, '..') && is_file("$root/$relative") ? "$root/$relative" : ($byName[$name] ?? null);

            if ($name === '' || $source === null) {
                $result['missing']++;

                continue;
            }

            $target = self::TARGET.($source === "$root/$relative" ? $relative : $name);
            $stream = fopen($source, 'rb');
            $disk->put($target, $stream);
            is_resource($stream) && fclose($stream);
            $photo->update(['file_path' => $target, 'url' => $disk->url($target)]);
            $result['found']++;
        }

        return $result;
    }
}

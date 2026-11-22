<?php

namespace App\Console\Commands;

use App\Domain\KnowledgeBase\Actions\ImportPhotoFiles;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kb:photos-import {katalog : Lokalna kopia wp-content/uploads}')]
#[Description('Kopiuje pliki zdjęć z katalogu na dysk public i przestawia adresy zdjęć na lokalne; można uruchamiać wielokrotnie')]
class ImportPhotos extends Command
{
    public function handle(ImportPhotoFiles $import): int
    {
        $directory = (string) $this->argument('katalog');
        if (! is_dir($directory)) {
            $this->components->error('Nie ma takiego katalogu.');

            return self::FAILURE;
        }

        $r = $import->handle($directory);
        $this->table(['Wynik', 'Liczba'], [['skopiowano (znaleziono)', $r['found']], ['brakujące pliki', $r['missing']], ['już lokalne', $r['already']]]);

        return self::SUCCESS;
    }
}

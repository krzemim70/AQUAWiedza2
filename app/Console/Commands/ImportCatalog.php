<?php

namespace App\Console\Commands;

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Data\ImportResult;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('kb:import {path : Katalog z plikami bazy wiedzy}')]
#[Description('Sprawdza paczkę plików bazy wiedzy, zapisuje ją jako partię i aktywuje')]
class ImportCatalog extends Command
{
    public function handle(ImportPackage $import): int
    {
        $result = $import->handle((string) $this->argument('path'));

        if ($result->status === ImportResult::FAILED) {
            $this->components->error('Import odrzucony, katalog bez zmian. Błędy: '.count($result->errors));
            foreach ($result->errors as $error) {
                $this->line(' - '.$error);
            }

            return self::FAILURE;
        }

        $batch = $result->batch;
        if ($result->status === ImportResult::UNCHANGED) {
            $this->components->info("Paczka już zaimportowana (partia #{$batch?->id}), bez zmian.");

            return self::SUCCESS;
        }

        $this->components->info("Zaimportowano i aktywowano partię #{$batch?->id}.");
        $this->table(['Obszar', 'Rekordy'], collect($batch?->counts)->map(fn ($n, $area) => [$area, $n])->values()->all());

        return self::SUCCESS;
    }
}

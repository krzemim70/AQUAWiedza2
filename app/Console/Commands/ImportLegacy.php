<?php

namespace App\Console\Commands;

use App\Legacy\LegacyImporter;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('legacy:import {--dry-run : Tylko raport, bez zapisu}')]
#[Description('Importuje dane ze starego WordPressa (połączenie legacy, tylko odczyt); można uruchamiać wielokrotnie')]
class ImportLegacy extends Command
{
    public function handle(LegacyImporter $importer): int
    {
        $dry = (bool) $this->option('dry-run');
        $report = $importer->run($dry);

        $rows = [];
        foreach ($report as $entity => $outcomes) {
            foreach ($outcomes as $outcome => $count) {
                $rows[] = [$entity, $outcome, $count];
            }
        }
        $this->table(['Encja', 'Wynik', 'Liczba'], $rows);
        $this->components->info($dry ? 'Próba bez zapisu: nic nie zapisano.' : 'Import zakończony.');

        return self::SUCCESS;
    }
}

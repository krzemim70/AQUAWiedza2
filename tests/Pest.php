<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

pest()->extend(TestCase::class)->use(RefreshDatabase::class)->in('Feature', 'Golden');

/** Kopia paczki plików bazy wiedzy z fixtures w świeżym katalogu (do modyfikowania w testach). */
function kbPackage(): string
{
    $dir = storage_path('framework/testing/kb-'.bin2hex(random_bytes(6)));
    mkdir($dir, 0777, true);
    foreach (glob(base_path('tests/Fixtures/KnowledgeBase/*.php')) as $file) {
        copy($file, $dir.DIRECTORY_SEPARATOR.basename($file));
    }

    return $dir;
}

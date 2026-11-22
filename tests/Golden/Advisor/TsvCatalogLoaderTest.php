<?php

use App\Domain\Advisor\Engine\TsvCatalogLoader;

it('loads the unchanged legacy catalogues described by the manifest', function () {
    $directory = __DIR__.'/Fixtures/';
    $manifest = json_decode(file_get_contents($directory.'manifest.json'), true, flags: JSON_THROW_ON_ERROR);
    $loader = new TsvCatalogLoader;

    foreach ($manifest as $file) {
        expect(hash_file('sha256', $directory.$file['filename']))->toBe($file['sha256'])
            ->and($loader->load($directory.$file['filename']))->toHaveCount($file['rows']);
    }
});

it('splits legacy multi-value fields', function () {
    expect(TsvCatalogLoader::splitMulti(' a ; ; 0 ; b '))->toBe(['a', '0', 'b'])
        ->and(TsvCatalogLoader::splitMulti(null))->toBe([]);
});

it('parses quoting, trimming and the legacy mojibake BOM', function () {
    $path = __DIR__.'/loader-test.tmp';
    try {
        file_put_contents($path, "\xC3\xAF\xC2\xBB\xC2\xBFid\tname\tvalues\n  X1  \t\"  a\tb  \"\t a ; b \n\tignored\t\n");
        expect((new TsvCatalogLoader)->load($path))->toBe([
            ['id' => 'X1', 'name' => "a\tb", 'values' => 'a ; b'],
        ]);
        file_put_contents($path, "\xEF\xBB\xBFid\tname\nX1\tvalue\n");
        expect((new TsvCatalogLoader)->load($path))->toBe([]);
    } finally {
        unlink($path);
    }
});

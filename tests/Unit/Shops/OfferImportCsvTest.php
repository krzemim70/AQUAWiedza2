<?php

use App\Domain\Shops\OfferImport\Csv;
use App\Domain\Shops\OfferImport\ImportRow;

it('reads both separators, quoted values and UTF-8 with or without a byte order mark', function (string $separator, string $header) {
    $file = $header.implode($separator, Csv::HEADERS)."\r\nFN0001{$separator}\"Mały{$separator} niebieski\"{$separator}\"12,30\"{$separator}2".str_repeat($separator, 4)."\r\n";
    $csv = (new Csv)->read($file, 1000, 1);

    expect($csv['separator'])->toBe($separator)
        ->and($csv['encoding'])->toBe('UTF-8')
        ->and($csv['rows'][2]['wariant'])->toBe("Mały{$separator} niebieski")
        ->and(ImportRow::price($csv['rows'][2]['cena']))->toBe(1230);
})->with([[';', ''], [',', "\xEF\xBB\xBF"]]);

it('converts Polish Windows-1250 and keeps physical line numbers for malformed rows', function () {
    $file = "id_rekordu;wariant;cena;ilosc\n\nFN0001;Żółty;12,30;2\nFN9999;sam\nFN0002;Duży;12.30;4";
    $csv = (new Csv)->read(iconv('UTF-8', 'Windows-1250', $file), 1000, 3);

    expect($csv['encoding'])->toBe('Windows-1250')
        ->and($csv['rows'][3]['wariant'])->toBe('Żółty')
        ->and($csv['rows'][4])->toBeNull()
        ->and($csv['rows'][5]['wariant'])->toBe('Duży');
});

it('rejects invalid encodings, repeated columns and limits by bytes and rows', function () {
    $csv = new Csv;
    $file = "id_rekordu;cena;ilosc\nFN0001;12;2\nFN0002;12;3";

    expect(fn () => $csv->read("\xff\xfei\x00", 1000, 2))->toThrow(InvalidArgumentException::class, 'kodowaniu')
        ->and(fn () => $csv->read('', 1000, 2))->toThrow(InvalidArgumentException::class, 'Brak nagłówka')
        ->and(fn () => $csv->read("id_rekordu;cena;cena;ilosc\nFN0001;12;12;2", 1000, 2))->toThrow(InvalidArgumentException::class, 'Nagłówek')
        ->and(fn () => $csv->read($file, 10, 2))->toThrow(InvalidArgumentException::class, 'bajtów')
        ->and(fn () => $csv->read($file, 1000, 1))->toThrow(InvalidArgumentException::class, 'wierszy danych');
});

it('keeps valid rows after an unclosed quote and supports quoted multiline fields', function () {
    $file = "id_rekordu;wariant;cena;ilosc\nFN0001;\"Duży\nM\";15;2\nFN0002;\"bez końca\nFN0003;Mały;15;3";
    $csv = (new Csv)->read($file, 1000, 3);

    expect($csv['rows'][2]['wariant'])->toBe("Duży\nM")
        ->and($csv['rows'][4])->toBeNull()
        ->and($csv['rows'][5]['wariant'])->toBe('Mały');
});

it('uses whole grosze, validates calendar dates and normalizes variant whitespace', function () {
    expect(ImportRow::price('0,5'))->toBe(50)
        ->and(ImportRow::price('1.234'))->toBeNull()
        ->and(ImportRow::price('1e2'))->toBeNull()
        ->and(ImportRow::date('2026-02-30', 'Europe/Warsaw'))->toBeNull()
        ->and(ImportRow::date('2026-07-01', 'Europe/Warsaw', endOfDay: true)?->format('Y-m-d H:i:s'))->toBe('2026-07-01 21:59:59')
        ->and(ImportRow::variantKey("  ŻÓŁTY\t  Duży  "))->toBe('żółty duży');
});

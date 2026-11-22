<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Batch;
use App\Domain\KnowledgeBase\Models\Photo;
use App\Domain\KnowledgeBase\Models\Record;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingVersion;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

it('returns nothing without an active batch', function () {
    Batch::query()->update(['state' => 'superseded', 'active_slot' => null]);

    expect(app(Catalog::class)->snapshot(Area::Fauna))->toBe([])
        ->and(app(Catalog::class)->find(Area::Fauna, 'FN0001'))->toBeNull()
        ->and(app(Catalog::class)->search('gupik'))->toBe([]);
});

it('returns a snapshot in file order with raw fields and split values', function () {
    $fauna = app(Catalog::class)->snapshot(Area::Fauna);

    expect($fauna[0]->id)->toBe('FN0001')->and($fauna[1]->id)->toBe('FN0002')
        ->and($fauna[0]->fields['Region'])->toBe('Ameryka Południowa')
        ->and($fauna[1]->values['WielkośćAkwarium'])->toBe(['Nano (< 40L)', 'Małe (40L - 100L)'])
        ->and($fauna[1]->fields['WielkośćAkwarium'])->toBe('Nano (< 40L);Małe (40L - 100L)')
        ->and($fauna[0]->description)->toBeNull();
});

it('finds a record by its exact case-sensitive key, with description and only verified photos', function () {
    $catalog = app(Catalog::class);

    $card = $catalog->find(Area::Fauna, 'FN0001');
    expect($card->description)->toStartWith('Gupik pawie oczko')->and($card->photos)->toBe([])
        ->and($catalog->find(Area::Fauna, 'fn0001'))->toBeNull()
        ->and($catalog->find(Area::Flora, 'FN0001'))->toBeNull();

    Photo::where('record_id', Record::where('legacy_id', 'FN0001')->value('id'))->update(['verified' => true]);

    expect($catalog->find(Area::Fauna, 'FN0001')->photos)->toHaveCount(1)
        ->and($catalog->find(Area::Fauna, 'FN0001')->photos[0]['author'])->toBe('Aquariumphoto.dk / JJ');
});

it('searches Polish and Latin names without case or diacritics and respects min chars', function () {
    $catalog = app(Catalog::class);
    $ids = fn (array $hits) => array_map(fn ($h) => $h->area->value.':'.$h->id, $hits);

    expect($ids($catalog->search('GUPIK pawie')))->toContain('fauna:FN0001')
        ->and($ids($catalog->search('poecilia reticulata')))->toContain('fauna:FN0001')
        ->and($ids($catalog->search('okraglolistna')))->toContain('flora:FL0001')
        ->and($ids($catalog->search('ultramax 1000')))->toContain('equipment:SP0001')
        ->and($catalog->search('gu'))->toBe([])
        ->and($catalog->search('  '))->toBe([])
        ->and($ids($catalog->search('rotala', [Area::Fauna])))->toBe([]);
});

it('limits search hits by setting', function () {
    expect(count(app(Catalog::class)->search('aqu')))->toBeGreaterThan(5);

    SettingVersion::create(['key' => SettingKey::SearchMaxHits->value, 'value' => 5, 'effective_from' => now()->subMinute(), 'reason' => 'test']);
    app(Settings::class)->forget(SettingKey::SearchMaxHits);

    expect(app(Catalog::class)->search('aqu'))->toHaveCount(5);
});

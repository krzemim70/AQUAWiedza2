<?php

use App\Domain\Advisor\CreatorBundle;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Photo;
use App\Domain\Settings\ChangeSetting;
use App\Domain\Settings\ChangeSettingData;
use App\Domain\Settings\SettingKey;
use App\Models\User;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('local');
});

it('serves an immutable JSON bundle without long article descriptions and under 250 KB', function () {
    app(ImportPackage::class)->handle(kbPackage());

    $bundle = app(CreatorBundle::class);
    $version = $bundle->currentVersion();

    expect($version)->not->toBe('empty')
        ->and(Storage::disk('local')->exists($bundle->path($version)))->toBeTrue();

    $response = $this->get(route('creator.bundle', ['version' => $version]));

    $response->assertOk()
        ->assertHeader('Content-Type', 'application/json; charset=UTF-8');

    $cacheControl = (string) $response->headers->get('Cache-Control');
    expect($cacheControl)->toContain('public', 'max-age=31536000', 'immutable');

    $raw = (string) $response->getContent();
    expect(strlen($raw))->toBeLessThan(250 * 1024)
        ->and($raw)->not->toContain('to jedna z najbardziej znanych ryb słodkowodnych utrzymywanych w akwariach');

    $data = json_decode($raw, true, flags: JSON_THROW_ON_ERROR);
    expect($data['version'])->toBe($version)
        ->and($data['fauna'])->toHaveCount(200)
        ->and($data['flora'])->toHaveCount(194)
        ->and($data['equipment'])->toHaveCount(108)
        ->and($data['care'])->toHaveCount(110)
        ->and($data['fauna'][0])->not->toHaveKey('description');
});

it('changes version on CompatSeverity update, removes old bundle file and redirects 302 from old URL', function () {
    app(ImportPackage::class)->handle(kbPackage());

    $bundle = app(CreatorBundle::class);
    $oldVersion = $bundle->currentVersion();
    expect(Storage::disk('local')->exists($bundle->path($oldVersion)))->toBeTrue();

    $admin = User::factory()->create(['is_admin' => true]);
    $severity = SettingKey::CompatSeverity->default();
    $severity['temperature_pair'] = 'conflict';

    app(ChangeSetting::class)->handle(new ChangeSettingData(
        $admin,
        SettingKey::CompatSeverity,
        $severity,
        'Zmiana testowa ważności temperatury',
    ));

    $newVersion = $bundle->currentVersion();
    expect($newVersion)->not->toBe($oldVersion)
        ->and(Storage::disk('local')->exists($bundle->path($newVersion)))->toBeTrue()
        ->and(Storage::disk('local')->exists($bundle->path($oldVersion)))->toBeFalse();

    $redirect = $this->get(route('creator.bundle', ['version' => $oldVersion]));
    $redirect->assertRedirect(route('creator.bundle', ['version' => $newVersion]));
    expect((string) $redirect->headers->get('Cache-Control'))->toContain('no-store');

    $this->get(route('creator.bundle', ['version' => $newVersion]))
        ->assertOk()
        ->assertJsonPath('rules.compatSeverity.temperature_pair', 'conflict');
});

it('serves an empty bundle when no active catalog batch exists', function () {
    $bundle = app(CreatorBundle::class);

    expect($bundle->currentVersion())->toBe('empty');

    $this->get(route('creator.bundle', ['version' => 'empty']))
        ->assertOk()
        ->assertJsonPath('version', 'empty')
        ->assertJsonPath('batchId', null)
        ->assertJsonPath('fauna', [])
        ->assertJsonPath('flora', [])
        ->assertJsonPath('equipment', [])
        ->assertJsonPath('care', []);
});

it('updates content signature and includes thumbnail when a photo is verified', function () {
    app(ImportPackage::class)->handle(kbPackage());

    $bundle = app(CreatorBundle::class);
    $before = $bundle->currentVersion();

    $photo = Photo::query()
        ->whereNotNull('url')
        ->whereHas('record', fn ($q) => $q->where('area', Area::Fauna->value))
        ->firstOrFail();
    $photo->update(['verified' => true, 'verified_at' => now()]);

    $after = $bundle->currentVersion();
    expect($after)->not->toBe($before);

    $payload = $this->get(route('creator.bundle', ['version' => $after]))->assertOk()->json();
    $thumbs = array_filter(array_column($payload['fauna'], 'thumb'));
    expect($thumbs)->not->toBeEmpty();
});

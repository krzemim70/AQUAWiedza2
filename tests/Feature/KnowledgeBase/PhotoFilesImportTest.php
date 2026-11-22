<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Actions\ImportPhotoFiles;
use App\Domain\KnowledgeBase\Models\Photo;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    app(ImportPackage::class)->handle(kbPackage());
    Storage::fake('public');
    $this->uploads = storage_path('framework/testing/uploads-'.bin2hex(random_bytes(6)));
    mkdir($this->uploads.'/2024/05', 0777, true);
});

afterEach(fn () => File::deleteDirectory($this->uploads));

it('copies matching files to the public disk, points photos at them and keeps missing ones', function () {
    $photos = Photo::orderBy('id')->take(3)->get();
    $photos[0]->update(['url' => 'https://stary.example/wp-content/uploads/2024/05/gupik.jpg', 'file_path' => null]);
    $photos[1]->update(['url' => 'https://stary.example/wp-content/uploads/2023/01/neon.jpg', 'file_path' => null]); // inny katalog, ta sama nazwa
    $photos[2]->update(['url' => 'https://stary.example/wp-content/uploads/2024/05/brak.jpg', 'file_path' => null]);
    file_put_contents($this->uploads.'/2024/05/gupik.jpg', 'a');
    file_put_contents($this->uploads.'/2024/05/neon.jpg', 'b');
    $before = Photo::count();

    Artisan::call('kb:photos-import', ['katalog' => $this->uploads]);

    expect(Photo::count())->toBe($before);
    Storage::disk('public')->assertExists('kb/2024/05/gupik.jpg');
    Storage::disk('public')->assertExists('kb/neon.jpg');
    expect($photos[0]->refresh()->url)->toBe(Storage::disk('public')->url('kb/2024/05/gupik.jpg'))
        ->and($photos[1]->refresh()->file_path)->toBe('kb/neon.jpg')
        ->and($photos[2]->refresh()->url)->toContain('stary.example');
});

it('is idempotent and reports found, missing and already local counts', function () {
    Photo::orderBy('id')->firstOrFail()->update(['url' => 'https://stary.example/wp-content/uploads/2024/05/gupik.jpg', 'file_path' => null]);
    file_put_contents($this->uploads.'/2024/05/gupik.jpg', 'a');

    $first = app(ImportPhotoFiles::class)->handle($this->uploads);
    $second = app(ImportPhotoFiles::class)->handle($this->uploads);

    expect($first['found'])->toBe(1)->and($first['missing'])->toBe(Photo::count() - 1)
        ->and($second['found'])->toBe(0)->and($second['already'])->toBe(1);
});

it('fails for a missing directory', function () {
    expect(Artisan::call('kb:photos-import', ['katalog' => $this->uploads.'/nie-ma']))->toBe(1);
});

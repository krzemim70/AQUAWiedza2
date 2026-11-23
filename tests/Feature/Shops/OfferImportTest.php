<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\ApplyOfferImport;
use App\Domain\Shops\Actions\PreviewOfferImport;
use App\Domain\Shops\Actions\PurgeOfferImports;
use App\Domain\Shops\Actions\SaveLocation;
use App\Domain\Shops\Actions\SaveOffer;
use App\Domain\Shops\Actions\SetAvailableQuantity;
use App\Domain\Shops\Actions\StartOfferImport;
use App\Domain\Shops\Data\LocationData;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Jobs\ProcessOfferImport;
use App\Domain\Shops\Models\ImportBatch;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\Models\StockMovement;
use App\Domain\Shops\Notifications\OfferImportNotice;
use App\Filament\Shop\Pages\OfferImport;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;

beforeEach(function () {
    Storage::fake('local');
    app(ImportPackage::class)->handle(kbPackage());
});

it('lets a staff member preview a file in the panel, confirm stock and read the result', function () {
    [$shop, $owner] = openShop();
    $staff = staffOf($shop, $owner);
    $url = OfferImport::getUrl(panel: 'shop', tenant: $shop);
    $this->actingAs($staff)->get($url)->assertOk()->assertSee('Pobierz wzór CSV');
    $template = $this->get(route('shop.offer-import.template', $shop))->assertDownload('wzor-ofert.csv');
    expect($template->getContent())->toContain('id_rekordu;wariant;cena;ilosc', 'FN0001', 'SP0046', 'CH0047');

    $file = UploadedFile::fake()->createWithContent('oferty.csv', $template->getContent());
    $this->from($url)->post(route('shop.offer-import.preview', $shop), ['file' => $file])->assertSessionHasNoErrors();
    $batch = ImportBatch::firstOrFail();
    $path = $batch->file_path;
    Storage::disk('local')->assertExists($path);
    $this->get($url.'?import='.$batch->id)->assertOk()->assertSee('Podgląd importu')->assertSee('FN0001')->assertSee('Główna');
    expect(Offer::count())->toBe(0);

    $apply = route('shop.offer-import.apply', [$shop, $batch->id]);
    $this->from($url.'?import='.$batch->id)->post($apply)->assertSessionHasErrors('confirmed');
    expect($batch->refresh()->state)->toBe('preview');
    $this->post($apply, ['confirmed' => '1'])->assertRedirect($url.'?import='.$batch->id);
    $this->get($url.'?import='.$batch->id)->assertOk()->assertSee('Import zakończony');
    expect($batch->refresh()->counts['added'])->toBe(3)
        ->and(Offer::where('state', OfferState::Published)->count())->toBe(2)
        ->and(Offer::where('state', OfferState::Draft)->count())->toBe(1)
        ->and(StockMovement::count())->toBe(3);
    Storage::disk('local')->assertMissing($path);
});

it('reports unknown records, ambiguous names, prices, duplicates and invalid dates and saves only valid rows', function () {
    Notification::fake();
    [$shop, $owner] = openShop();
    $csv = "id_rekordu;wariant;cena;ilosc;wazna_od\n"
        ."FN9999;;12;2;\nEquilibrium;;12;2;\nFN0001;;0,50;1;\n"
        ."SP0046;Duży M;12;1;\nSP0046; DUŻY   M ;12;1;\nCH0047;;12;1;2026-02-30\n"
        ."FN0002;;12;1;\n";
    $batch = app(PreviewOfferImport::class)->handle($owner, $shop, $csv);
    expect($batch->counts['errors'])->toBe(6)
        ->and($batch->report['errors'][3][0])->toContain('kilku rekordów')
        ->and($batch->report['errors'][5])->toBe($batch->report['errors'][6]);
    $url = OfferImport::getUrl(['import' => $batch->id], panel: 'shop', tenant: $shop);
    $this->actingAs($owner)->get($url)->assertOk()->assertSee('Brak takiego ID')->assertSee('Błąd')->assertSee('Ta sama oferta występuje');
    $result = $this->get(route('shop.offer-import.errors', [$shop, $batch->id]))->assertDownload('bledy-importu.csv');
    expect($result->streamedContent())->toContain('wiersz;powod', 'Użyj ID rekordu.');

    app(StartOfferImport::class)->handle($owner, $shop, $batch->id, true);
    expect($batch->refresh()->counts)->toMatchArray(['added' => 1, 'updated' => 0, 'unchanged' => 0, 'errors' => 6])
        ->and(Offer::sole()->legacy_id)->toBe('FN0002');
    Notification::assertSentTo($owner, OfferImportNotice::class);
});

it('matches a unique exact name and the normalized variant and changes quantity through the stock action', function () {
    [$shop, $owner, $location] = openShop();
    $from = now()->subHour()->startOfSecond();
    $to = now()->addDay()->startOfSecond();
    $offer = app(SaveOffer::class)->handle(
        $owner, $shop, new OfferData($location->id, Area::Fauna, 'FN0001', 'Mały M', 1500, $from, $to, 1),
    );
    $name = app(Catalog::class)->labels(Area::Fauna, ['FN0001'])['FN0001'];
    $batch = app(PreviewOfferImport::class)->handle($owner, $shop, "id_rekordu;wariant;cena;ilosc;publikuj\n\"$name\"; MAŁY   M ;16,55;9;nie");
    expect($batch->counts['updated'])->toBe(1)
        ->and($batch->report['rows'][2]['previous_price'])->toBe(1500);
    app(StartOfferImport::class)->handle($owner, $shop, $batch->id, true);

    expect(Offer::count())->toBe(1)
        ->and($offer->refresh()->price_grosze)->toBe(1655)
        ->and($offer->free_qty)->toBe(9)
        ->and($offer->valid_to->equalTo($to))->toBeTrue()
        ->and($offer->movements()->first()->kind)->toBe('adjust')
        ->and($offer->movements()->first()->delta_free)->toBe(8);
});

it('skips stale existing and newly created offers while committing other rows', function () {
    Notification::fake();
    [$shop, $owner, $loc] = openShop();
    $existing = publishedOffer($owner, $shop, $loc, 'FN0001');
    $csv = "id_rekordu;cena;ilosc\nFN0001;20;4\nFN0002;25;7\nFN0003;30;5";
    $preview = app(PreviewOfferImport::class)->handle($owner, $shop, $csv);
    app(SetAvailableQuantity::class)->handle($owner, $existing, $existing->version, 8, true);
    draftOffer($owner, $shop, $loc, 'FN0002');

    app(StartOfferImport::class)->handle($owner, $shop, $preview->id, true);
    expect($preview->refresh()->report['errors'])->toBe([
        2 => ['oferta zmieniła się od podglądu'], 3 => ['oferta zmieniła się od podglądu'],
    ])->and($existing->refresh()->free_qty)->toBe(8)
        ->and($existing->price_grosze)->toBe(1500)
        ->and(Offer::where('legacy_id', 'FN0003')->sole()->state)->toBe(OfferState::Published);
});

it('enforces the publication limit at preview and rolls back each row if publication later fails', function () {
    Notification::fake();
    shopSetting(SettingKey::ShopActivation, ['threshold' => 1, 'offer_limit' => 2]);
    [$shop, $owner, $loc] = openShop();
    $draft = draftOffer($owner, $shop, $loc, 'FN0002');
    $csv = "id_rekordu;cena;ilosc\nFN0001;12;2\nFN0002;20;9\nFN0003;12;1";
    $batch = app(PreviewOfferImport::class)->handle($owner, $shop, $csv);
    expect($batch->report['errors'][4][0])->toContain('limit 2');

    publishedOffer($owner, $shop, $loc, 'FN0004');
    app(StartOfferImport::class)->handle($owner, $shop, $batch->id, true);
    expect($batch->refresh()->report['errors'][3][0])->toContain('limit 2')
        ->and($batch->counts['added'])->toBe(1)
        ->and($draft->refresh()->price_grosze)->toBe(1500)
        ->and($draft->free_qty)->toBe(5)
        ->and($draft->movements()->count())->toBe(1)
        ->and(Offer::where('legacy_id', 'FN0001')->sole()->state)->toBe(OfferState::Published);
});

it('isolates previews, approvals and downloads between shops', function () {
    [$shop, $owner] = openShop();
    [$other, $outsider] = openShop('7740001454', 'Drugi');
    $batch = app(PreviewOfferImport::class)->handle($owner, $shop, "id_rekordu;cena;ilosc\nFN0001;15;1");
    $this->actingAs($outsider)->get(OfferImport::getUrl(['import' => $batch->id], panel: 'shop', tenant: $shop))->assertNotFound();
    $this->post(route('shop.offer-import.preview', $shop))->assertForbidden();
    $this->post(route('shop.offer-import.apply', [$shop, $batch->id]), ['confirmed' => '1'])->assertForbidden();
    $this->get(route('shop.offer-import.errors', [$shop, $batch->id]))->assertForbidden();
    $this->post(route('shop.offer-import.apply', [$other, $batch->id]), ['confirmed' => '1'])->assertNotFound();
    expect($batch->refresh()->state)->toBe('preview');
});

it('rejects uploads above configured byte and row limits', function () {
    [$shop, $owner] = openShop();
    shopSetting(SettingKey::ShopCsvImport, ['max_bytes' => 60, 'max_rows' => 1]);
    $url = route('shop.offer-import.preview', $shop);
    $this->actingAs($owner)->from(OfferImport::getUrl(panel: 'shop', tenant: $shop))
        ->post($url, ['file' => UploadedFile::fake()->createWithContent('oferty.csv', str_repeat('a', 61))])
        ->assertSessionHasErrors(['file' => 'Plik może mieć najwyżej 60 bajtów.']);
    $this->post($url, ['file' => UploadedFile::fake()->createWithContent('oferty.csv', "id_rekordu;cena;ilosc\nFN0001;15;1\nFN0002;15;1")])
        ->assertSessionHasErrors(['file' => 'Plik może zawierać najwyżej 1 wierszy danych.']);
    expect(ImportBatch::count())->toBe(0);
});

it('reads a comma CSV in Windows-1250 and requires a location when several are active', function () {
    [$shop, $owner] = openShop();
    $branch = app(SaveLocation::class)->handle($owner, $shop, new LocationData('Łódź', 'Główna 1', 'Łódź'));
    $csv = "id_rekordu,wariant,cena,ilosc,placowka\nFN0001,Żółty,\"12,30\",2,Łódź\nFN0002,,12,1,";
    $preview = app(PreviewOfferImport::class)->handle($owner, $shop, iconv('UTF-8', 'Windows-1250', $csv));
    $this->actingAs($owner)->get(OfferImport::getUrl(['import' => $preview->id], panel: 'shop', tenant: $shop))
        ->assertOk()->assertSee('Windows-1250')->assertSee('separator przecinek')->assertSee('Żółty')->assertSee('Podaj jednoznaczną nazwę');
    Notification::fake();
    app(StartOfferImport::class)->handle($owner, $shop, $preview->id, true);
    expect(Offer::sole()->location_id)->toBe($branch->id);
});

it('queues a large import and retries safely across chunks', function () {
    Queue::fake();
    [$shop, $owner] = openShop();
    $csv = "id_rekordu;wariant;cena;ilosc;publikuj\n".implode("\n", array_map(fn (int $n) => "FN0001;Rozmiar $n;15;1;nie", range(1, 101)));
    $batch = app(PreviewOfferImport::class)->handle($owner, $shop, $csv);
    expect($batch->report['rows'])->toHaveCount(50);
    $start = app(StartOfferImport::class);
    $start->handle($owner, $shop, $batch->id, true);
    $start->handle($owner, $shop, $batch->id, true);
    Queue::assertPushed(ProcessOfferImport::class, 1);
    expect($batch->refresh()->state)->toBe('queued')->and(Offer::count())->toBe(0);
    $this->actingAs($owner)->get(OfferImport::getUrl(['import' => $batch->id], panel: 'shop', tenant: $shop))
        ->assertOk()->assertSee('Odśwież wynik');
    inShopPanel($shop, $owner);
    $page = Livewire::withQueryParams(['import' => (string) $batch->id])->test(OfferImport::class)
        ->assertSet('waiting', true);

    $job = new ProcessOfferImport($batch->id);
    $apply = app(ApplyOfferImport::class);
    $job->handle($apply);
    Queue::assertPushed(ProcessOfferImport::class, 2);
    expect($batch->refresh()->counts['added'])->toBe(100)->and($batch->state)->toBe('queued');
    $job->handle($apply);
    $job->handle($apply);
    $page->call('refreshImport')->assertSet('waiting', false)->assertSee('Import zakończony');
    expect($batch->refresh()->state)->toBe('applied')
        ->and($batch->counts['added'])->toBe(101)
        ->and(Offer::count())->toBe(101)
        ->and(StockMovement::count())->toBe(101);
});

it('cleans up abandoned previews when their configured retention expires', function () {
    [$shop, $owner] = openShop();
    shopSetting(SettingKey::ShopCsvImport, ['max_bytes' => 100, 'max_rows' => 1, 'retention_hours' => 1]);
    $batch = app(PreviewOfferImport::class)->handle($owner, $shop, "id_rekordu;cena;ilosc\nFN0001;15;1");
    $path = $batch->file_path;
    $this->travel(2)->hours();
    app(PurgeOfferImports::class)->handle();

    expect($batch->fresh())->toBeNull();
    Storage::disk('local')->assertMissing($path);
});

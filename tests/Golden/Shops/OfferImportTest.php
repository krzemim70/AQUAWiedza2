<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\Shops\Actions\PreviewOfferImport;
use App\Domain\Shops\Actions\StartOfferImport;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\OfferState;
use App\Domain\Shops\Models\StockMovement;
use Illuminate\Support\Facades\Storage;

it('imports three real records across catalogue areas and reimport leaves their history intact', function () {
    Storage::fake('local');
    app(ImportPackage::class)->handle(kbPackage());
    [$shop, $owner] = openShop();
    $csv = "id_rekordu;wariant;cena;ilosc\nFN0001;Mały;15,00;5\nSP0046;;129,90;2\nCH0047;;27,50;0";
    $preview = app(PreviewOfferImport::class)->handle($owner, $shop, $csv);

    expect($preview->counts)->toMatchArray(['added' => 3, 'updated' => 0, 'unchanged' => 0, 'errors' => 0])
        ->and(Offer::count())->toBe(0);
    app(StartOfferImport::class)->handle($owner, $shop, $preview->id, true);

    expect($preview->refresh()->state)->toBe('applied')
        ->and(Offer::orderBy('id')->pluck('legacy_id')->all())->toBe(['FN0001', 'SP0046', 'CH0047'])
        ->and(Offer::where('state', OfferState::Published)->count())->toBe(3)
        ->and(Offer::orderBy('id')->pluck('price_grosze')->all())->toBe([1500, 12990, 2750])
        ->and(StockMovement::where('kind', 'opening')->count())->toBe(3);

    $repeat = app(PreviewOfferImport::class)->handle($owner, $shop, $csv);
    expect($repeat->counts['unchanged'])->toBe(3);
    app(StartOfferImport::class)->handle($owner, $shop, $repeat->id, true);

    expect($repeat->refresh()->counts['unchanged'])->toBe(3)
        ->and(Offer::count())->toBe(3)
        ->and(StockMovement::count())->toBe(3);
});

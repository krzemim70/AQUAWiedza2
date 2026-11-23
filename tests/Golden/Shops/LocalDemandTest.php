<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Shops\Actions\ListLocalDemand;
use App\Domain\Shops\Actions\SaveLocation;
use App\Domain\Shops\Actions\WatchRecord;
use App\Domain\Shops\Data\LocationData;
use App\Domain\Shops\Models\RecordWatch;
use App\Filament\Shop\Pages\LocalDemand;
use App\Models\User;

it('shows only qualifying city and catalogue record counts without watcher details', function () {
    app(ImportPackage::class)->handle(kbPackage());
    [$shop, $owner] = openShop();
    [$otherShop, $otherOwner, $otherLocation] = openShop('7740001454', 'Drugi sklep');
    $locations = app(SaveLocation::class);
    $locations->handle($owner, $shop, new LocationData('Filia', 'Rynek 2', 'Gdańsk'));
    $locations->handle($owner, $shop, new LocationData('Druga placówka', 'Rynek 3', 'Poznań'));
    $locations->handle($otherOwner, $otherShop, new LocationData('Główna', 'Rynek 4', 'Warszawa'), $otherLocation);
    $staff = staffOf($shop, $owner);
    $watch = app(WatchRecord::class);

    $local = User::factory()->count(5)->create(['city' => 'POZNAN']);
    foreach ($local as $i => $watcher) {
        $watch->handle($watcher, Area::Fauna, 'FN0001');
        $watch->handle($watcher, Area::Fauna, 'FN0002');
        $watch->handle($watcher, Area::Fauna, 'FN0003');
        if ($i === 4) {
            RecordWatch::where('user_id', $watcher->id)->where('legacy_id', 'FN0002')->update(['notified_at' => now()]);
            RecordWatch::where('user_id', $watcher->id)->where('legacy_id', 'FN0003')->update(['removed_at' => now()]);
        }
    }

    foreach (User::factory()->count(5)->create(['city' => 'Gdańsk']) as $watcher) {
        $watch->handle($watcher, Area::Flora, 'FL0001');
    }
    foreach (User::factory()->count(5)->create(['city' => 'Warszawa']) as $watcher) {
        $watch->handle($watcher, Area::Fauna, 'FN0002');
    }
    $watch->handle(aquarist(), Area::Fauna, 'FN0001');

    expect(app(ListLocalDemand::class)->handle($staff, $shop))->toBe([
        ['city' => 'Gdańsk', 'records' => [['id' => 'FL0001', 'name' => 'Rotala okrągłolistna', 'area' => Area::Flora, 'count' => 5]]],
        ['city' => 'Poznań', 'records' => [['id' => 'FN0001', 'name' => 'Gupik Pawie Oczko', 'area' => Area::Fauna, 'count' => 5]]],
    ]);

    $response = $this->actingAs($staff)->get(LocalDemand::getUrl(panel: 'shop', tenant: $shop))->assertOk()
        ->assertSee('Gdańsk')->assertSee('FL0001')->assertSee('Poznań')->assertSee('FN0001')
        ->assertDontSee('Warszawa')->assertDontSee('FN0002')->assertDontSee('FN0003');
    foreach ($local as $watcher) {
        $response->assertDontSee($watcher->name)->assertDontSee($watcher->email);
    }
});

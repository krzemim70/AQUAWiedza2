<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Shops\Actions\ListLocalDemand;
use App\Domain\Shops\Actions\WatchRecord;
use App\Filament\Shop\Pages\LocalDemand;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;

it('uses the current visibility threshold from the demand setting', function () {
    app(ImportPackage::class)->handle(kbPackage());
    [$shop, $owner] = openShop();
    $url = LocalDemand::getUrl(panel: 'shop', tenant: $shop);

    foreach (User::factory()->count(2)->create(['city' => 'Poznań']) as $watcher) {
        app(WatchRecord::class)->handle($watcher, Area::Fauna, 'FN0001');
    }

    $this->actingAs($owner)->get($url)->assertOk()
        ->assertSee('Za mało danych')->assertDontSee('FN0001');

    shopSetting(SettingKey::DemandMetrics, ['k' => 2, 'retention_days' => 400, 'region' => 'city']);
    $this->get($url)->assertOk()->assertSee('FN0001')->assertDontSee('Za mało danych');
});

it('denies access outside the team of the requested shop', function () {
    [$shop, $owner] = openShop();
    [, $otherOwner] = openShop('7740001454', 'Drugi sklep');
    $outsider = aquarist();
    $url = LocalDemand::getUrl(panel: 'shop', tenant: $shop);

    $this->actingAs(staffOf($shop, $owner))->get($url)->assertOk()->assertSee('Popyt w okolicy');
    $this->actingAs($outsider)->get($url)->assertForbidden();
    $this->actingAs($otherOwner)->get($url)->assertNotFound();
    expect(fn () => app(ListLocalDemand::class)->handle($otherOwner, $shop))->toThrow(AuthorizationException::class);
});

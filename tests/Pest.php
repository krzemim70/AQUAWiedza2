<?php

use App\Domain\Aquarium\Actions\CreateTank;
use App\Domain\Aquarium\Actions\SaveMeasurement;
use App\Domain\Aquarium\Data\MeasurementData;
use App\Domain\Aquarium\Data\TankData;
use App\Domain\Aquarium\Models\Measurement;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Domain\Settings\SettingVersion;
use App\Domain\Shops\Actions\AddMember;
use App\Domain\Shops\Actions\OpenShop;
use App\Domain\Shops\Actions\PublishOffer;
use App\Domain\Shops\Actions\SaveLocation;
use App\Domain\Shops\Actions\SaveOffer;
use App\Domain\Shops\Data\LocationData;
use App\Domain\Shops\Data\OfferData;
use App\Domain\Shops\Models\Location;
use App\Domain\Shops\Models\Offer;
use App\Domain\Shops\Models\Shop;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Database\Eloquent\Model;
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

function aquarist(bool $fishroom = false): User
{
    return User::factory()->create($fishroom ? ['fishroom_ends_at' => now()->addDay()] : []);
}

function tankData(string $name = 'Salon', string $size = 'medium'): TankData
{
    return new TankData($name, 'Ogólne / Towarzyskie', $size);
}

function tankFor(User $user, string $name = 'Salon'): Tank
{
    return app(CreateTank::class)->handle($user, tankData($name));
}

/** @param array<string, string|null> $values */
function measure(User $user, int $tankId, array $values, ?Carbon\Carbon $at = null): Measurement
{
    return app(SaveMeasurement::class)->handle($user, $tankId, new MeasurementData($at ?? now(), $values));
}

/** @return list<string> id rekordów fauny z aktywnego katalogu */
function faunaIds(int $n = 3): array
{
    return array_slice(array_map(fn ($e) => $e->id, app(Catalog::class)->snapshot(Area::Fauna)), 0, $n);
}

function shopSetting(SettingKey $key, mixed $value): void
{
    SettingVersion::create(['key' => $key->value, 'value' => $value, 'effective_from' => now()->subMinute(), 'reason' => 'test']);
    app(Settings::class)->forget($key);
}

/** @return array{0: Shop, 1: User, 2: Location} */
function openShop(string $nip = '5260250274', string $name = 'Sklep'): array
{
    $owner = User::factory()->shop()->create();
    $shop = app(OpenShop::class)->handle($owner, $name, $nip);
    $location = app(SaveLocation::class)->handle($owner, $shop, new LocationData('Główna', 'Rynek 1', 'Poznań'));

    return [$shop, $owner, $location];
}

function offerData(Location $location, string $id, int $price = 1500, int $qty = 5, string $variant = ''): OfferData
{
    return new OfferData($location->id, Area::Fauna, $id, $variant, $price, now()->subHour(), now()->addDays(30), $qty);
}

function draftOffer(User $actor, Shop $shop, Location $location, string $id, int $qty = 5): Offer
{
    return app(SaveOffer::class)->handle($actor, $shop, offerData($location, $id, qty: $qty));
}

function publishedOffer(User $actor, Shop $shop, Location $location, string $id, int $qty = 5): Offer
{
    return app(PublishOffer::class)->handle($actor, draftOffer($actor, $shop, $location, $id, $qty));
}

function staffOf(Shop $shop, User $owner): User
{
    $staff = User::factory()->create();
    app(AddMember::class)->handle($owner, $shop, $staff->email);

    return $staff;
}

function inShopPanel(Model $shop, User $user): void
{
    Filament::setCurrentPanel('shop');
    test()->actingAs($user);
    Filament::setTenant($shop);
    Filament::bootCurrentPanel();
}

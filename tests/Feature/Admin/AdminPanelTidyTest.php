<?php

use App\Domain\IdentityAccess\Models\ShopApplication;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Batch;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Domain\KnowledgeBase\Models\Photo;
use App\Domain\Shops\Models\Order;
use App\Domain\Shops\Models\Review;
use App\Domain\Shops\Models\ShopState;
use App\Filament\Pages\SettingsPage;
use App\Filament\Resources\Batches\BatchResource;
use App\Filament\Resources\Batches\Pages\ListBatches;
use App\Filament\Resources\Entries\EntryResource;
use App\Filament\Resources\Entries\Pages\ListEntries;
use App\Filament\Resources\Entries\Pages\ViewEntry;
use App\Filament\Resources\Orders\OrderResource;
use App\Filament\Resources\Orders\Pages\ListOrders;
use App\Filament\Resources\Photos\Pages\ListPhotos;
use App\Filament\Resources\Photos\PhotoResource;
use App\Filament\Resources\RecordSpecs\RecordSpecResource;
use App\Filament\Resources\Reviews\Pages\ListReviews;
use App\Filament\Resources\Reviews\ReviewResource;
use App\Filament\Resources\ShopApplications\Pages\ListShopApplications;
use App\Filament\Resources\ShopApplications\ShopApplicationResource;
use App\Filament\Resources\Shops\Pages\ListShops;
use App\Filament\Resources\Shops\ShopResource;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Testing\TestAction;
use Filament\Facades\Filament;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Carbon;
use Livewire\Livewire;

it('sets Polish as default locale and translates base validation messages', function () {
    expect(config('app.locale'))->toBe('pl')
        ->and(config('app.fallback_locale'))->toBe('pl')
        ->and(trans('validation.required', ['attribute' => 'powód']))->toBe('Pole powód jest wymagane.');
});

it('configures admin panel brand name, navigation groups, order, icons and labels', function () {
    $admin = User::factory()->admin()->create();
    Filament::setCurrentPanel('admin');
    $this->actingAs($admin, 'admin');
    Filament::bootCurrentPanel();

    $panel = Filament::getPanel('admin');

    expect($panel->getBrandName())->toBe('Aikwarium — panel administratora')
        ->and($panel->getNavigationGroups())->toBe(['Konta i sklepy', 'Baza wiedzy', 'System']);

    $navigation = $panel->getNavigation();
    $groupLabels = array_values(array_filter(
        array_map(fn ($group) => $group->getLabel(), array_values($navigation)),
        fn (?string $label): bool => $label !== null,
    ));
    expect($groupLabels)->toBe(['Konta i sklepy', 'Baza wiedzy', 'System']);

    expect(UserResource::getNavigationGroup())->toBe('Konta i sklepy')
        ->and(UserResource::getNavigationSort())->toBe(1)
        ->and(UserResource::getNavigationIcon())->toBe(Heroicon::OutlinedUsers)
        ->and(ShopApplicationResource::getNavigationGroup())->toBe('Konta i sklepy')
        ->and(ShopApplicationResource::getNavigationSort())->toBe(2)
        ->and(ShopApplicationResource::getNavigationIcon())->toBe(Heroicon::OutlinedClipboardDocumentCheck)
        ->and(ShopResource::getNavigationGroup())->toBe('Konta i sklepy')
        ->and(ShopResource::getNavigationSort())->toBe(3)
        ->and(ShopResource::getNavigationIcon())->toBe(Heroicon::OutlinedBuildingStorefront)
        ->and(OrderResource::getNavigationGroup())->toBe('Konta i sklepy')
        ->and(OrderResource::getNavigationSort())->toBe(4)
        ->and(OrderResource::getNavigationIcon())->toBe(Heroicon::OutlinedShoppingBag)
        ->and(ReviewResource::getNavigationGroup())->toBe('Konta i sklepy')
        ->and(ReviewResource::getNavigationSort())->toBe(5)
        ->and(ReviewResource::getNavigationIcon())->toBe(Heroicon::OutlinedChatBubbleLeftRight)
        ->and(EntryResource::getNavigationGroup())->toBe('Baza wiedzy')
        ->and(EntryResource::getNavigationSort())->toBe(1)
        ->and(EntryResource::getNavigationIcon())->toBe(Heroicon::OutlinedBookOpen)
        ->and(PhotoResource::getNavigationGroup())->toBe('Baza wiedzy')
        ->and(PhotoResource::getNavigationSort())->toBe(2)
        ->and(PhotoResource::getNavigationIcon())->toBe(Heroicon::OutlinedPhoto)
        ->and(BatchResource::getNavigationGroup())->toBe('Baza wiedzy')
        ->and(BatchResource::getNavigationSort())->toBe(3)
        ->and(BatchResource::getNavigationIcon())->toBe(Heroicon::OutlinedArrowUpTray)
        ->and(BatchResource::getNavigationLabel())->toBe('Importy katalogu')
        ->and(RecordSpecResource::getNavigationGroup())->toBe('Baza wiedzy')
        ->and(RecordSpecResource::getNavigationSort())->toBe(4)
        ->and(RecordSpecResource::getNavigationIcon())->toBe(Heroicon::OutlinedDocumentCheck)
        ->and(SettingsPage::getNavigationGroup())->toBe('System')
        ->and(SettingsPage::getNavigationSort())->toBe(1)
        ->and(SettingsPage::getNavigationIcon())->toBe(Heroicon::OutlinedCog6Tooth);
});

it('forbids non-admin and suspended admin users from viewing admin resources and pages', function () {
    app(ImportPackage::class)->handle(kbPackage());
    $entry = Entry::query()->inActiveBatch()->firstOrFail();
    $targetUser = User::factory()->create();
    [$shop] = openShop();
    $application = ShopApplication::create([
        'user_id' => $targetUser->id,
        'company_name' => 'Akwarystyka Test',
        'nip' => '5260250274',
        'address' => 'ul. Testowa 1',
        'phone' => '500600700',
    ]);
    $review = Review::create(['shop_id' => $shop->id, 'rating' => 5, 'comment' => 'Świetny sklep']);
    $batch = Batch::query()->active()->firstOrFail();
    $photo = Photo::query()->firstOrFail();
    $order = new Order;

    foreach ([aquarist(fishroom: true), User::factory()->admin()->create(['suspended_at' => now()])] as $actor) {
        Filament::setCurrentPanel('admin');
        $this->actingAs($actor, 'admin');

        expect(UserResource::canViewAny())->toBeFalse()
            ->and(UserResource::canView($targetUser))->toBeFalse()
            ->and(UserResource::canEdit($targetUser))->toBeFalse()
            ->and(UserResource::canDelete($targetUser))->toBeFalse()
            ->and(ShopApplicationResource::canViewAny())->toBeFalse()
            ->and(ShopApplicationResource::canEdit($application))->toBeFalse()
            ->and(ShopApplicationResource::canDelete($application))->toBeFalse()
            ->and(ShopResource::canViewAny())->toBeFalse()
            ->and(OrderResource::canViewAny())->toBeFalse()
            ->and(OrderResource::canEdit($order))->toBeFalse()
            ->and(ReviewResource::canViewAny())->toBeFalse()
            ->and(EntryResource::canViewAny())->toBeFalse()
            ->and(EntryResource::canView($entry))->toBeFalse()
            ->and(PhotoResource::canViewAny())->toBeFalse()
            ->and(PhotoResource::canEdit($photo))->toBeFalse()
            ->and(PhotoResource::canDelete($photo))->toBeFalse()
            ->and(BatchResource::canViewAny())->toBeFalse()
            ->and(BatchResource::canEdit($batch))->toBeFalse()
            ->and(BatchResource::canDelete($batch))->toBeFalse()
            ->and(SettingsPage::canAccess())->toBeFalse();

        Livewire::test(ListUsers::class)->assertForbidden();
        Livewire::test(ViewUser::class, ['record' => $targetUser->getKey()])->assertForbidden();
        Livewire::test(ListShopApplications::class)->assertForbidden();
        Livewire::test(ListShops::class)->assertForbidden();
        Livewire::test(ListOrders::class)->assertForbidden();
        Livewire::test(ListReviews::class)->assertForbidden();
        Livewire::test(ListEntries::class)->assertForbidden();
        Livewire::test(ViewEntry::class, ['record' => $entry->getKey()])->assertForbidden();
        Livewire::test(ListPhotos::class)->assertForbidden();
        Livewire::test(ListBatches::class)->assertForbidden();
        Livewire::test(SettingsPage::class)->assertForbidden();
    }
});

it('requires confirmation on sensitive admin actions and handles domain and validation errors', function () {
    $admin = User::factory()->admin()->create();
    Filament::setCurrentPanel('admin');
    $this->actingAs($admin, 'admin');

    $unverified = User::factory()->unverified()->create();
    $fishroom = User::factory()->create(['fishroom_ends_at' => now()->addDays(10)]);
    $suspended = User::factory()->create(['suspended_at' => now()->subDay()]);

    Livewire::test(ListUsers::class)
        ->assertTableActionExists('activate', fn (Action $action): bool => $action->isConfirmationRequired(), $unverified)
        ->assertTableActionExists('grantFishroom', fn (Action $action): bool => $action->isConfirmationRequired(), $unverified)
        ->assertTableActionExists('revokeFishroom', fn (Action $action): bool => $action->isConfirmationRequired(), $fishroom)
        ->assertTableActionExists('suspend', fn (Action $action): bool => $action->isConfirmationRequired(), $fishroom)
        ->assertTableActionExists('unsuspend', fn (Action $action): bool => $action->isConfirmationRequired(), $suspended)
        ->assertActionExists(TestAction::make('activate')->table()->bulk(), fn (Action $action): bool => $action->isConfirmationRequired())
        ->callTableAction('activate', $unverified, ['reason' => 'Weryfikacja ręczna'])
        ->assertNotified('Aktywowano konto')
        ->callTableAction('grantFishroom', $fishroom, ['ends_at' => now()->subDay()->toDateString(), 'reason' => 'Test daty'])
        ->assertNotified('Podaj datę końca Fishroom w przyszłości.');

    expect($unverified->refresh()->hasVerifiedEmail())->toBeTrue();

    $bulkTarget = User::factory()->unverified()->create();
    Livewire::test(ListUsers::class)
        ->callTableBulkAction('activate', [$bulkTarget], ['reason' => 'Aktywacja zbiorcza'])
        ->assertNotified('Aktywowano wybrane konta');

    expect($bulkTarget->refresh()->hasVerifiedEmail())->toBeTrue();

    [$shop] = openShop();
    $visibleReview = Review::create(['shop_id' => $shop->id, 'rating' => 5, 'comment' => 'Polecam']);
    $hiddenReview = Review::create(['shop_id' => $shop->id, 'rating' => 2, 'comment' => 'Spam', 'hidden_reason' => 'Spam']);

    Livewire::test(ListReviews::class)
        ->assertTableActionExists('hide', fn (Action $action): bool => $action->isConfirmationRequired(), $visibleReview)
        ->assertTableActionExists('restore', fn (Action $action): bool => $action->isConfirmationRequired(), $hiddenReview);

    $applicant = User::factory()->shop()->create();
    $application = ShopApplication::create([
        'user_id' => $applicant->id,
        'company_name' => 'Sklep Do Weryfikacji',
        'nip' => '5260250274',
        'address' => 'ul. Weryfikacyjna 1',
        'phone' => '500600700',
    ]);

    Livewire::test(ListShopApplications::class)
        ->assertTableActionExists('approve', fn (Action $action): bool => $action->isConfirmationRequired(), $application)
        ->assertTableActionExists('reject', fn (Action $action): bool => $action->isConfirmationRequired(), $application);

    $shop->forceFill(['state' => ShopState::Suspended])->save();
    Livewire::test(ListShops::class)
        ->assertTableActionExists('exception', fn (Action $action): bool => $action->isConfirmationRequired(), $shop)
        ->assertTableActionExists('resume', fn (Action $action): bool => $action->isConfirmationRequired(), $shop);

    $pendingBatch = Batch::create([
        'state' => Batch::PENDING,
        'files' => [],
        'counts' => ['fauna' => 1],
        'errors' => [],
    ]);

    Livewire::test(ListBatches::class)
        ->assertSee('Importy katalogu')
        ->assertTableActionExists('activate', fn (Action $action): bool => $action->isConfirmationRequired(), $pendingBatch);

    Livewire::test(SettingsPage::class)
        ->assertTableActionExists('change', fn (Action $action): bool => $action->isConfirmationRequired(), 'fishroom.price_grosze');
});

it('displays timestamps in configured display timezone across admin tables and infolist', function () {
    $admin = User::factory()->admin()->create();
    Filament::setCurrentPanel('admin');
    $this->actingAs($admin, 'admin');

    $utc = Carbon::parse('2026-06-15 10:00:00', 'UTC');
    $this->travelTo($utc);

    $user = User::factory()->create([
        'email_verified_at' => $utc,
        'fishroom_ends_at' => $utc,
    ]);
    [$shop] = openShop();
    $shop->forceFill(['activated_at' => $utc])->save();
    ShopApplication::create([
        'user_id' => $user->id,
        'company_name' => 'Sklep Strefowy',
        'nip' => '5260250274',
        'address' => 'ul. Czasowa 1',
        'phone' => '500600700',
    ]);
    Batch::create([
        'state' => Batch::PENDING,
        'files' => [],
        'counts' => ['fauna' => 1],
        'errors' => [],
        'activated_at' => $utc,
    ]);

    // 10:00 UTC w czerwcu = 12:00 Europe/Warsaw (CEST)
    Livewire::test(ListUsers::class)->assertSee('12:00');
    Livewire::test(ViewUser::class, ['record' => $user->getKey()])->assertSee('12:00');
    Livewire::test(ListShopApplications::class)->assertSee('12:00');
    Livewire::test(ListShops::class)->assertSee('12:00');
    Livewire::test(ListBatches::class)->assertSee('12:00');
});

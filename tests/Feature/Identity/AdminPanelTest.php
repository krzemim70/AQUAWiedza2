<?php

use App\Domain\IdentityAccess\Models\AccessChange;
use App\Domain\IdentityAccess\Models\ShopApplication;
use App\Domain\IdentityAccess\Models\Tier;
use App\Filament\Resources\ShopApplications\Pages\ListShopApplications;
use App\Filament\Resources\Users\Pages\ListUsers;
use App\Filament\Resources\Users\Pages\ViewUser;
use App\Models\User;
use Filament\Facades\Filament;
use Livewire\Livewire;

beforeEach(function () {
    Filament::setCurrentPanel('admin');
    $this->actingAs($this->admin = User::factory()->admin()->create());
});

it('lists ALL accounts, including one without any access data, and filters them', function () {
    $tank = User::factory()->create(); // brak danych dostępu
    $fish = User::factory()->create(['fishroom_ends_at' => now()->addMonth()]);
    $suspended = User::factory()->create(['suspended_at' => now()]);
    $pending = User::factory()->unverified()->create();
    $shop = User::factory()->shop()->create();

    Livewire::test(ListUsers::class)
        ->assertCanSeeTableRecords([$this->admin, $tank, $fish, $suspended, $pending, $shop])
        ->filterTable('level', 'fishroom')->assertCanSeeTableRecords([$fish])->assertCanNotSeeTableRecords([$tank, $shop])
        ->filterTable('level', 'tank')->assertCanSeeTableRecords([$tank, $shop])->assertCanNotSeeTableRecords([$fish])
        ->resetTableFilters()->filterTable('account_state', 'suspended')->assertCanSeeTableRecords([$suspended])->assertCanNotSeeTableRecords([$tank])
        ->resetTableFilters()->filterTable('account_state', 'pending')->assertCanSeeTableRecords([$pending])->assertCanNotSeeTableRecords([$tank])
        ->resetTableFilters()->filterTable('type', 'shop')->assertCanSeeTableRecords([$shop])->assertCanNotSeeTableRecords([$tank])
        ->resetTableFilters()->filterTable('verified', false)->assertCanSeeTableRecords([$pending])->assertCanNotSeeTableRecords([$tank])
        ->resetTableFilters()->searchTable($fish->email)->assertCanSeeTableRecords([$fish])->assertCanNotSeeTableRecords([$tank]);
});

it('grants Fishroom, revokes it, suspends and unsuspends with a reason and shows the history', function () {
    $user = User::factory()->create();
    $end = now()->addDays(10)->toDateString();

    Livewire::test(ListUsers::class)
        ->callTableAction('grantFishroom', $user, ['ends_at' => $end, 'reason' => 'płatność BLIK'])
        ->assertHasNoTableActionErrors();
    expect($user->refresh()->tier())->toBe(Tier::Fishroom)->and($user->fishroom_ends_at->toDateString())->toBe($end);

    Livewire::test(ListUsers::class)
        ->callTableAction('revokeFishroom', $user, ['reason' => 'zwrot'])
        ->callTableAction('suspend', $user, ['reason' => 'spam'])
        ->callTableAction('unsuspend', $user, ['reason' => 'pomyłka']);
    expect($user->refresh()->tier())->toBe(Tier::Tank)->and($user->isSuspended())->toBeFalse()
        ->and(AccessChange::where('user_id', $user->id)->count())->toBe(4);

    Livewire::test(ViewUser::class, ['record' => $user->id])->assertSee('płatność BLIK')->assertSee('Zawieszono konto');
});

it('requires a reason for access actions', function () {
    $user = User::factory()->create();

    Livewire::test(ListUsers::class)
        ->callTableAction('suspend', $user, ['reason' => ''])->assertHasTableActionErrors(['reason' => 'required']);
    expect($user->refresh()->isSuspended())->toBeFalse();
});

it('approves and rejects shop applications', function () {
    $make = function () {
        $u = User::factory()->shop()->create();

        return ShopApplication::create(['user_id' => $u->id, 'company_name' => 'X', 'nip' => '5260250274', 'address' => 'A', 'phone' => '1']);
    };
    [$a, $b] = [$make(), $make()];

    Livewire::test(ListShopApplications::class)
        ->callTableAction('approve', $a, ['reason' => 'ok'])
        ->callTableAction('reject', $b, ['reason' => 'brak firmy']);

    expect($a->refresh()->status)->toBe(ShopApplication::APPROVED)
        ->and($a->user->tier())->toBe(Tier::Tank)
        ->and($b->refresh()->status)->toBe(ShopApplication::REJECTED);
});

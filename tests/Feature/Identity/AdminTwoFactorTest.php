<?php

use App\Filament\Auth\AdminLogin;
use App\Models\User;
use Filament\Auth\MultiFactor\App\AppAuthentication;
use Filament\Facades\Filament;
use Illuminate\Support\Facades\Auth;
use Livewire\Livewire;

beforeEach(function () {
    $panel = Filament::getPanel('admin');
    expect($panel->isMultiFactorAuthenticationRequired())->toBeTrue();
    Filament::setCurrentPanel($panel);
});

it('requires an administrator who signed in with only a password to set up two-factor authentication', function () {
    $admin = User::factory()->admin()->create();

    Livewire::test(AdminLogin::class)
        ->set(['data.email' => $admin->email, 'data.password' => 'password'])
        ->call('authenticate')
        ->assertRedirect();

    $setupUrl = Filament::getSetUpRequiredMultiFactorAuthenticationUrl();
    $this->get('/admin')->assertRedirect($setupUrl);
    $this->get('/admin/uzytkownicy')->assertRedirect($setupUrl);
    $this->get($setupUrl)->assertOk()->assertSee('Skonfiguruj uwierzytelnianie dwuskładnikowe');

    $admin->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());
    Auth::forgetGuards();
    $this->get('/admin')->assertRedirect('/admin/login');
});

it('asks for a code after the password and opens the panel after a valid code', function () {
    $admin = User::factory()->admin()->create();
    $totp = AppAuthentication::make();
    $admin->saveAppAuthenticationSecret($totp->generateSecret());

    $login = Livewire::test(AdminLogin::class)
        ->set(['data.email' => $admin->email, 'data.password' => 'password'])
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertNoRedirect();
    $this->assertGuest('admin');
    $this->get('/admin')->assertRedirect('/admin/login');

    $login->set('data.multiFactor.app.code', $totp->getCurrentCode($admin))
        ->call('authenticate')
        ->assertHasNoErrors()
        ->assertRedirect();

    $this->assertAuthenticatedAs($admin, 'admin');
    $this->get('/admin')->assertOk();
});

it('cannot use the public login session as the administrative second factor', function () {
    $admin = User::factory()->admin()->create();
    $admin->saveAppAuthenticationSecret(AppAuthentication::make()->generateSecret());

    $this->post('/logowanie', ['email' => $admin->email, 'password' => 'password'])->assertRedirect('/admin');
    $this->assertAuthenticatedAs($admin, 'web');
    $this->get('/admin')->assertRedirect('/admin/login');
    $this->assertGuest('admin');
});

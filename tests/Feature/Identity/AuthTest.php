<?php

use App\Domain\IdentityAccess\Models\ShopApplication;
use App\Domain\Shops\Actions\OpenShop;
use App\Models\User;
use Filament\Facades\Filament;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;

function approvedShop(string $status = ShopApplication::APPROVED): User
{
    $user = User::factory()->shop()->create(['password' => 'password']);
    ShopApplication::forceCreate(['user_id' => $user->id, 'company_name' => 'X', 'nip' => '5260250274', 'address' => 'A', 'phone' => '1', 'status' => $status]);
    if ($status === ShopApplication::APPROVED) {
        app(OpenShop::class)->handle($user, 'X', '5260250274');
    }

    return $user;
}

it('redirects after login by role', function (Closure $make, string $to) {
    $user = $make();

    $this->post('/logowanie', ['email' => $user->email, 'password' => 'password'])->assertRedirect($to);
    $this->assertAuthenticatedAs($user);
})->with([
    'admin' => [fn () => User::factory()->admin()->create(['password' => 'password']), '/admin'],
    'approved shop' => [fn () => approvedShop(), '/panel-sklepu'],
    'pending shop' => [fn () => approvedShop(ShopApplication::PENDING), '/moje-akwarium'],
    'aquarist' => [fn () => User::factory()->create(['password' => 'password']), '/moje-akwarium'],
]);

it('refuses wrong passwords and unverified accounts with no session', function () {
    $user = User::factory()->create(['password' => 'password']);
    $this->post('/logowanie', ['email' => $user->email, 'password' => 'zle'])->assertSessionHasErrors('email');

    $unverified = User::factory()->unverified()->create(['password' => 'password']);
    $this->post('/logowanie', ['email' => $unverified->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->assertGuest();
});

it('blocks a suspended account with the one access message and no session', function () {
    $user = User::factory()->create(['password' => 'password', 'suspended_at' => now()]);

    $this->post('/logowanie', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Konto jest zawieszone. Skontaktuj się z administratorem.']);
    $this->assertGuest();
});

it('logs out', function () {
    $this->actingAs(User::factory()->create())->post('/wyloguj')->assertRedirect('/');
    $this->assertGuest();
});

it('throttles login attempts', function () {
    $user = User::factory()->create();
    foreach (range(1, 5) as $_) {
        $this->post('/logowanie', ['email' => $user->email, 'password' => 'zle']);
    }
    $this->post('/logowanie', ['email' => $user->email, 'password' => 'password'])
        ->assertSessionHasErrors(['email' => 'Zbyt wiele prób logowania. Spróbuj za chwilę.'])
        ->assertSessionMissing('_old_input.password');
    $this->assertGuest();
});

it('grants panel access by role', function (Closure $make, bool $admin, bool $shop) {
    $user = $make();

    expect($user->canAccessPanel(Filament::getPanel('admin')))->toBe($admin)
        ->and($user->canAccessPanel(Filament::getPanel('shop')))->toBe($shop);
})->with([
    'admin' => [fn () => User::factory()->admin()->create(), true, true],
    'approved shop' => [fn () => approvedShop(), false, true],
    'pending shop' => [fn () => approvedShop(ShopApplication::PENDING), false, false],
    'rejected shop' => [fn () => approvedShop(ShopApplication::REJECTED), false, false],
    'aquarist' => [fn () => User::factory()->create(), false, false],
]);

it('sends a password reset notification and resets the password', function () {
    Notification::fake();
    $user = User::factory()->create();

    $this->post('/haslo/email', ['email' => $user->email])->assertSessionHas('status');
    $this->post('/haslo/email', ['email' => 'nie-ma@example.test'])->assertSessionHas('status'); // no enumeration
    Notification::assertSentTo($user, ResetPassword::class);
    Notification::assertCount(1);

    $token = Password::createToken($user);
    $this->post('/haslo/reset', ['token' => $token, 'email' => $user->email, 'password' => 'nowe-haslo-123', 'password_confirmation' => 'nowe-haslo-123'])
        ->assertRedirect('/logowanie');
    $this->post('/logowanie', ['email' => $user->email, 'password' => 'nowe-haslo-123'])->assertRedirect('/moje-akwarium');
});

it('serves registration, reset and verification pages', function (string $url) {
    $this->get($url)->assertOk();
})->with(['/rejestracja', '/rejestracja/akwarysta', '/rejestracja/sklep', '/haslo/reset', '/weryfikacja']);

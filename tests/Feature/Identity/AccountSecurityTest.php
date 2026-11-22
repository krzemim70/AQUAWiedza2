<?php

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

it('keeps only needed registration input when rejecting a submission', function (bool $honeypot) {
    $email = 'test@example.com';

    $this->from('/rejestracja/akwarysta')->post('/rejestracja/akwarysta', [
        'email' => $email,
        'fax' => $honeypot ? 'spam' : '',
        'form_token' => Crypt::encryptString((string) now()->subMinute()->timestamp),
        'password' => 'testowe-haslo',
        'password_confirmation' => 'inna-wartosc',
        'current_password' => 'poprzednie-haslo',
    ])->assertRedirect('/rejestracja/akwarysta')
        ->assertSessionHasErrors($honeypot ? 'form' : 'password')
        ->assertSessionHasInput('email', $email)
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation')
        ->assertSessionMissing('_old_input.current_password');
})->with(['honeypot' => true, 'validation' => false]);

it('does not flash any password from an invalid account form', function () {
    $this->actingAs(User::factory()->create())
        ->post('/moje-konto/haslo', [
            'current_password' => 'błędne-hasło',
            'password' => 'testowe-haslo',
            'password_confirmation' => 'testowe-haslo',
        ])
        ->assertSessionHasErrors('current_password')
        ->assertSessionMissing('_old_input.current_password')
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation');
});

it('does not flash passwords or the token when password reset validation fails', function () {
    $this->post('/haslo/reset', [
        'email' => 'test@example.com',
        'token' => 'testowy-token',
        'password' => 'testowe-haslo',
        'password_confirmation' => 'inna-wartosc',
    ])->assertSessionHasErrors('password')
        ->assertSessionHasInput('email', 'test@example.com')
        ->assertSessionMissing(['_old_input.token', '_old_input.password', '_old_input.password_confirmation']);
});

it('regenerates the active session and rejects another session after a password change', function () {
    $user = User::factory()->create(['password' => 'stare-haslo-123']);
    $this->post('/logowanie', ['email' => $user->email, 'password' => 'stare-haslo-123'])->assertRedirect();

    $oldSessionId = session()->getId();
    $oldCredentials = session()->only([Auth::guard('web')->getName(), 'password_hash_web']);
    $oldRememberToken = $user->getRememberToken();

    $this->post('/moje-konto/haslo', [
        'current_password' => 'stare-haslo-123',
        'password' => 'nowe-haslo-456',
        'password_confirmation' => 'nowe-haslo-456',
    ])->assertSessionHas('status');

    expect(session()->getId())->not->toBe($oldSessionId)
        ->and(session('password_hash_web'))->not->toBe($oldCredentials['password_hash_web'])
        ->and($user->fresh()->getRememberToken())->not->toBe($oldRememberToken);
    $this->get('/moje-konto')->assertOk();

    Auth::forgetGuards();
    $this->withSession($oldCredentials)->get('/moje-konto')->assertRedirect('/logowanie');
    $this->assertGuest();
});

it('denies access and ends the active session on the next request after suspension', function () {
    $user = User::factory()->create();
    $this->post('/logowanie', ['email' => $user->email, 'password' => 'password'])->assertRedirect();

    $user->forceFill(['suspended_at' => now()])->save();
    Auth::forgetGuards();

    $this->post('/moje-konto/nazwa', ['name' => 'Zmieniona nazwa'])->assertForbidden();
    $this->assertGuest();
    expect($user->fresh()->name)->toBe($user->name);
});

it('limits the password reset and verification forms independently', function (string $url, string $other) {
    for ($attempt = 0; $attempt < 6; $attempt++) {
        $this->post($url)->assertRedirect();
    }

    $this->post($url)->assertTooManyRequests()->assertSee('Zbyt wiele prób')->assertHeader('Retry-After');
    $this->post($other)->assertRedirect();
})->with([
    ['/haslo/email', '/haslo/reset'],
    ['/haslo/reset', '/weryfikacja/ponow'],
    ['/weryfikacja/ponow', '/haslo/email'],
]);

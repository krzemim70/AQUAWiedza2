<?php

use App\Domain\IdentityAccess\Models\Consent;
use App\Domain\IdentityAccess\Models\ShopApplication;
use App\Domain\IdentityAccess\Nip;
use App\Domain\Settings\ChangeSetting;
use App\Domain\Settings\ChangeSettingData;
use App\Domain\Settings\SettingKey;
use App\Models\User;
use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;

const VALID_NIP = '5260250274';

function regForm(array $over = [], int $age = 10): array
{
    return [
        'form_token' => Crypt::encryptString((string) now()->subSeconds($age)->timestamp),
        'name' => 'Jan Kowalski', 'email' => 'jan@example.test',
        'password' => 'sekret-haslo', 'password_confirmation' => 'sekret-haslo',
        'terms' => '1', 'privacy' => '1', 'adult' => '1', 'fax' => '',
        ...$over,
    ];
}

function shopForm(array $over = []): array
{
    return regForm([
        'company_name' => 'Akwa Sp. z o.o.', 'nip' => '526-025-02-74', 'address' => 'Długa 1, 00-001 Warszawa',
        'phone' => '+48 600 100 200', ...$over,
    ]);
}

it('registers an aquarist, records consents and sends the verification mail', function () {
    Notification::fake();

    $this->post('/rejestracja/akwarysta', regForm(['newsletter' => '1']))->assertRedirect('/rejestracja/gotowe');

    $user = User::firstWhere('email', 'jan@example.test');
    expect($user->type->value)->toBe('aquarist')->and($user->hasVerifiedEmail())->toBeFalse()
        ->and($user->adult_declared_at)->not->toBeNull()
        ->and(Consent::where('user_id', $user->id)->where('granted', true)->pluck('document')->sort()->values()->all())->toBe(['adult', 'newsletter', 'privacy', 'terms'])
        ->and(Consent::where('user_id', $user->id)->pluck('document_version')->unique()->all())->toBe(['1']);
    Notification::assertSentTo($user, VerifyEmail::class);
});

it('stores the optional newsletter consent as not granted by default', function () {
    $this->post('/rejestracja/akwarysta', regForm())->assertRedirect('/rejestracja/gotowe');

    expect(Consent::where('document', 'newsletter')->value('granted'))->toBeFalse();
});

it('registers a shop with a pending application', function () {
    $this->post('/rejestracja/sklep', shopForm())->assertRedirect('/rejestracja/gotowe');

    $user = User::firstWhere('email', 'jan@example.test');
    expect($user->type->value)->toBe('shop')->and($user->tier()->value)->toBe('tank')
        ->and($user->shopApplication->nip)->toBe(VALID_NIP)
        ->and($user->shopApplication->status)->toBe(ShopApplication::PENDING);
});

it('does not reveal an existing e-mail', function () {
    User::factory()->create(['email' => 'jan@example.test']);

    $this->post('/rejestracja/akwarysta', regForm())->assertRedirect('/rejestracja/gotowe')->assertSessionHasNoErrors();
    expect(User::count())->toBe(1);
});

it('rejects honeypot, too fast, too slow and tampered submissions without creating accounts', function (array $over, int $age) {
    $this->from('/rejestracja/akwarysta')->post('/rejestracja/akwarysta', regForm($over, $age))
        ->assertRedirect('/rejestracja/akwarysta')->assertSessionHasErrors('form');
    expect(User::count())->toBe(0);
})->with([
    'honeypot' => [['fax' => 'x'], 10],
    'too fast' => [[], 1],
    'too slow' => [[], 4000],
    'bad token' => [['form_token' => 'zly'], 10],
]);

it('limits registration attempts per IP using the setting', function () {
    RateLimiter::clear('register:127.0.0.1');

    foreach (range(1, 5) as $i) {
        $this->post('/rejestracja/akwarysta', regForm(['email' => "u$i@example.test"]))->assertRedirect('/rejestracja/gotowe');
    }

    $this->post('/rejestracja/akwarysta', regForm(['email' => 'u6@example.test']))
        ->assertTooManyRequests()->assertSee('Zbyt wiele prób')->assertHeader('Retry-After');
    expect(User::count())->toBe(5);
});

it('validates the NIP checksum', function (string $nip, bool $ok) {
    expect(Nip::isValid($nip))->toBe($ok);
    $this->post('/rejestracja/sklep', shopForm(['nip' => $nip]));
    expect(User::count())->toBe($ok ? 1 : 0);
})->with([['5260250274', true], ['526-025-02-74', true], ['5260250275', false], ['123', false]]);

it('requires terms, privacy acknowledgement and adult confirmation', function (string $field) {
    $this->post('/rejestracja/akwarysta', regForm([$field => '']))->assertSessionHasErrors($field);
    expect(User::count())->toBe(0);
})->with(['terms', 'privacy', 'adult']);

it('requires terms and respects the minimum password length from settings', function () {
    $this->post('/rejestracja/akwarysta', regForm(['terms' => '']))->assertSessionHasErrors('terms');
    $this->post('/rejestracja/akwarysta', regForm(['password' => 'krotki', 'password_confirmation' => 'krotki']))->assertSessionHasErrors('password');
    expect(User::count())->toBe(0);
});

it('activates and signs in a new account when verification is disabled', function () {
    Notification::fake();
    $admin = User::factory()->admin()->create();
    app(ChangeSetting::class)->handle(new ChangeSettingData($admin, SettingKey::EmailVerificationRequired, false, 'Test'));

    $this->post('/rejestracja/akwarysta', regForm())->assertRedirect('/moje-akwarium');

    $user = User::firstWhere('email', 'jan@example.test');
    expect($user->hasVerifiedEmail())->toBeTrue();
    $this->assertAuthenticatedAs($user);
    Notification::assertNotSentTo($user, VerifyEmail::class);
});

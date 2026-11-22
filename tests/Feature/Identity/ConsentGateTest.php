<?php

use App\Domain\IdentityAccess\Models\Consent;
use App\Domain\Settings\SettingKey;
use App\Models\User;

it('sends an account without consent to the acceptance page from every aquarist area', function () {
    $user = User::factory()->create();
    Consent::where('user_id', $user->id)->delete();

    foreach (['/moje-akwarium', '/moje-konto', route('advisor.tank'), route('advisor.guided'), '/koszyk'] as $path) {
        $this->actingAs($user)->get($path)->assertRedirect('/zgody');
    }
    $this->post(route('advisor.guided.answer'), ['key' => 'goal', 'code' => 'fauna'])->assertRedirect('/zgody');
    $this->get('/zgody')->assertOk()->assertSee('Akceptuję regulamin')->assertSee('Zapisz i przejdź dalej');
});

it('records consents for the current version and lets the account through', function () {
    $user = User::factory()->create();
    Consent::where('user_id', $user->id)->delete();
    $user->forceFill(['adult_declared_at' => null])->save();

    $this->actingAs($user)->post('/zgody', ['terms' => 1, 'privacy' => 1])->assertSessionHasErrors('adult');
    $this->post('/zgody', ['terms' => 1, 'privacy' => 1, 'adult' => 1])->assertRedirect();

    expect(Consent::where('user_id', $user->id)->where('granted', true)->pluck('document')->sort()->values()->all())->toBe(['adult', 'privacy', 'terms'])
        ->and(Consent::where('user_id', $user->id)->where('document', 'newsletter')->value('granted'))->toBeFalse()
        ->and($user->refresh()->adult_declared_at)->not->toBeNull();
    $this->get('/moje-akwarium')->assertOk();
    $this->get('/zgody')->assertRedirect('/moje-akwarium');
});

it('asks again after the document version changes', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->get('/moje-akwarium')->assertOk();

    shopSetting(SettingKey::ConsentVersion, '2');

    $this->get('/moje-akwarium')->assertRedirect('/zgody');
    $this->post('/zgody', ['terms' => 1, 'privacy' => 1, 'adult' => 1, 'newsletter' => 1]);
    expect(Consent::where('user_id', $user->id)->where('document_version', '2')->count())->toBe(4);
    $this->get('/moje-akwarium')->assertOk();
});

it('does not block the admin and shop panels', function () {
    $admin = User::factory()->admin()->create();
    Consent::where('user_id', $admin->id)->delete();
    $this->actingAs($admin)->get('/admin')->assertSuccessful();

    [$shop, $owner] = openShop();
    Consent::where('user_id', $owner->id)->delete();
    $this->actingAs($owner)->get("/panel-sklepu/{$shop->id}")->assertOk();
});

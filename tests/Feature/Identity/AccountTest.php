<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

it('requires login', function () {
    $this->get('/moje-konto')->assertRedirect(route('login'));
});

it('shows account data, level and shops', function () {
    $user = User::factory()->create(['name' => 'Jan Kowalski', 'fishroom_ends_at' => now()->addDays(5)]);
    $this->actingAs($user)->get('/moje-konto')->assertOk()
        ->assertSee('Jan Kowalski')->assertSee($user->email)->assertSee('Fishroom')->assertSee(now()->addDays(5)->format('d.m.Y'))
        ->assertDontSee('Sklepy:');
    $this->actingAs($user)->get('/doradca')->assertSee('Moje konto');

    [, $owner] = openShop(name: 'Rybka');
    $this->actingAs($owner)->get('/moje-konto')->assertSee('Sklepy: Rybka');
});

it('changes the name', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post('/moje-konto/nazwa', ['name' => 'Nowa Nazwa'])->assertSessionHas('status');
    expect($user->fresh()->name)->toBe('Nowa Nazwa');
    $this->actingAs($user)->post('/moje-konto/nazwa', ['name' => ''])->assertSessionHasErrors('name');
});

it('changes the password only with the current one', function () {
    $user = User::factory()->create(['password' => 'stare-haslo-1']);
    $new = ['password' => 'nowe-haslo-123', 'password_confirmation' => 'nowe-haslo-123'];

    $this->actingAs($user)->post('/moje-konto/haslo', $new + ['current_password' => 'zle'])->assertSessionHasErrors('current_password');
    expect(Hash::check('stare-haslo-1', $user->fresh()->password))->toBeTrue();

    $this->actingAs($user)->post('/moje-konto/haslo', $new + ['current_password' => 'stare-haslo-1'])->assertSessionHas('status');
    expect(Hash::check('nowe-haslo-123', $user->fresh()->password))->toBeTrue();
});

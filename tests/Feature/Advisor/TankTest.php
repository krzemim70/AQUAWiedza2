<?php

use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Models\User;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

const FULL_SMALL = ['size' => 'small', 'fish' => 'small', 'temperature' => 'warm', 'ph' => 'neutral', 'type' => 'Ogólne / Towarzyskie'];

it('requires login and follows the access matrix', function () {
    $this->get('/doradca')->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get('/doradca')->assertOk()->assertSee('Doradca');
    $this->actingAs(User::factory()->create(['fishroom_ends_at' => now()->addDay()]))->get('/doradca')->assertOk();
    $this->actingAs(User::factory()->create(['suspended_at' => now()]))->get('/doradca')->assertForbidden();
});

it('shows only the form before a tank profile is given', function () {
    $this->actingAs(User::factory()->create())->get('/doradca')
        ->assertSee('Wielkość akwarium (wymagane)')->assertSee('Typ akwarium')->assertDontSee('Ocenionych gatunków');
});

it('presents a group of equal best species with a per-species "learn more" link and the rest by name and percent', function () {
    $this->actingAs(User::factory()->create())->get('/doradca?'.http_build_query(FULL_SMALL))->assertOk()
        ->assertSee('11 równorzędnych gatunków z wynikiem 100%')
        ->assertSee('Błyszczyk parański')->assertSee('Dowiedz się więcej')
        ->assertSee('Razbora zielonooka — 93%')
        ->assertSee('Ocenionych gatunków: 142, pominiętych: 58')
        ->assertDontSee('<h4>Dowiedz się więcej</h4>', false);
});

it('shows the description of one chosen card from the top group and nothing for others', function () {
    $user = User::factory()->create();
    $query = http_build_query(FULL_SMALL);

    $this->actingAs($user)->get("/doradca?$query&card=FN0001")->assertSee('Gupik pawie oczko', false)->assertSee('<h4>Dowiedz się więcej</h4>', false);
    // FN0188 jest poza grupą najlepszych, więc nie odsłaniamy jego opisu
    $this->actingAs($user)->get("/doradca?$query&card=FN0188")->assertDontSee('<h4>Dowiedz się więcej</h4>', false);
});

it('shows the winner with its description and the criterion that decided an equal percent', function () {
    $query = http_build_query(['size' => 'nano', 'fish' => 'medium', 'temperature' => 'cool', 'ph' => 'neutral', 'type' => 'Ogólne / Towarzyskie']);

    $this->actingAs(User::factory()->create())->get("/doradca?$query")
        ->assertSee('Polecany gatunek:')->assertSee('(80%)')
        ->assertSee('wygrywa na kryterium: temperatura')
        ->assertSee('<h4>Dowiedz się więcej</h4>', false);
});

it('derives the fish size from the tank when it is empty and says so', function () {
    $this->actingAs(User::factory()->create())->get('/doradca?size=nano&fish=&temperature=&ph=&type=')
        ->assertSee('Wielkość ryby dopasowana do zbiornika (Nano (do 3 cm))')
        ->assertSee('Ocenionych gatunków: 135, pominiętych: 65', false);
});

it('validates the profile', function () {
    $user = User::factory()->create();

    $this->actingAs($user)->from('/doradca')->get('/doradca?size=')->assertSessionHasErrors('size');
    $this->actingAs($user)->from('/doradca')->get('/doradca?size=nano&type=x')->assertSessionHasErrors('type');
});

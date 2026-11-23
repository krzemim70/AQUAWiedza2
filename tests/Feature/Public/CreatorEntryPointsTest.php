<?php

use App\Domain\Aquarium\Models\TankPlan;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Livewire\Aquarium\PlanIndex;
use Livewire\Livewire;

beforeEach(function () {
    app(ImportPackage::class)->handle(kbPackage());
});

it('exposes the creator entry point on home, advisor, aquarium index and plans index', function () {
    $creatorUrl = route('creator.show');

    $this->get('/')->assertOk()
        ->assertSee($creatorUrl, false)
        ->assertSee('Kreator akwarium')
        ->assertSee(route('drop.show'), false);

    $this->get(route('advisor'))->assertOk()
        ->assertSee($creatorUrl, false)
        ->assertSee('Zaplanuj nowe akwarium krok po kroku')
        ->assertSee('Krok po kroku')
        ->assertSee('Dobierz do mojego zbiornika')
        ->assertSee('Szybki dobór');

    $user = aquarist();

    $this->actingAs($user)->get(route('advisor'))->assertOk()
        ->assertSee($creatorUrl, false)
        ->assertSee('Zaplanuj nowe akwarium krok po kroku');

    $this->actingAs($user)->get(route('aquarium.index'))->assertOk()
        ->assertSee($creatorUrl, false)
        ->assertSee('Otwórz interaktywny kreator')
        ->assertSee(route('plans.index'), false)
        ->assertSee('Twoje zapisane plany')
        ->assertSee('flex-wrap');

    $this->actingAs($user)->get(route('plans.index'))->assertOk()
        ->assertSee($creatorUrl, false)
        ->assertSee('Wolisz dobrać obsadę, rośliny i sprzęt krok po kroku?')
        ->assertSee('Otwórz kreator nowego akwarium')
        ->assertSee('Nowy plan');
});

it('marks the creator link as active in navigation when on /kreator-akwarium', function () {
    $html = $this->get(route('creator.show'))->assertOk()->getContent();

    expect(preg_match('~href="'.preg_quote(route('creator.show'), '~').'"\s+aria-current="page"\s*>Kreator</a>~', (string) $html))->toBe(1);
});

it('keeps /dobor, simple plan Livewire creation and /moje-akwarium/plany/{plan} working', function () {
    $this->get(route('drop.show'))->assertOk()
        ->assertSee('Wielkość akwarium (wymagane)');

    $user = aquarist();

    $component = Livewire::actingAs($user)->test(PlanIndex::class)
        ->set('name', 'Salonowy plan')
        ->set('type', 'Ogólne / Towarzyskie')
        ->set('size_band', 'medium')
        ->call('create')
        ->assertHasNoErrors();

    $plan = TankPlan::ownedBy($user)->sole();
    expect($plan->name)->toBe('Salonowy plan');

    $component->assertRedirect(route('plans.show', ['plan' => $plan->id]));

    $this->actingAs($user)->get(route('plans.show', $plan->id))->assertOk()
        ->assertSee('Salonowy plan');
});

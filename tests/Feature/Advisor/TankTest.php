<?php

use App\Domain\Aquarium\Actions\CreateTank;
use App\Domain\Aquarium\Data\TankData;
use App\Domain\Aquarium\Models\Livestock;
use App\Domain\Aquarium\Models\Tank;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Livewire\Aquarium\TankShow;
use App\Models\User;
use Livewire\Livewire;

beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

/** @param array{0: string, 1: string, 2: string} $prefs ryba, temperatura, pH */
function advisorTank(User $user, string $size = 'small', string $name = 'Salon', array $prefs = ['small', 'warm', 'neutral']): Tank
{
    [$fish, $temp, $ph] = $prefs;

    return app(CreateTank::class)->handle($user, new TankData($name, 'Ogólne / Towarzyskie', $size, $fish, $temp, $ph));
}

it('requires login and follows the access matrix', function () {
    $this->get(route('advisor.tank'))->assertRedirect(route('login'));
    $this->actingAs(User::factory()->create())->get(route('advisor.tank'))->assertOk()->assertSee('Doradca');
    $this->actingAs(User::factory()->create(['fishroom_ends_at' => now()->addDay()]))->get(route('advisor.tank'))->assertOk();
    $this->actingAs(User::factory()->create(['suspended_at' => now()]))->get(route('advisor.tank'))->assertForbidden();
});

it('invites a user without a tank to create one', function () {
    $this->actingAs(User::factory()->create())->get(route('advisor.tank'))
        ->assertSee('Nie masz jeszcze zbiornika')->assertSee(route('aquarium.index'), false)->assertDontSee('Ocenionych gatunków');
});

it('ranks the first tank by default using its saved bands', function () {
    $user = User::factory()->create();
    advisorTank($user);

    $this->actingAs($user)->get(route('advisor.tank'))->assertOk()
        ->assertSee('Zbiornik: Salon')
        ->assertSee('11 równorzędnych gatunków z wynikiem 100%')
        ->assertSee('Błyszczyk parański')->assertSee('Dowiedz się więcej')
        ->assertSee('Razbora zielonooka — 93%')
        ->assertSee('Ocenionych gatunków: 142, pominiętych: 58')
        ->assertDontSee('Jak Doradca to policzył')
        ->assertDontSee('<h4>Dowiedz się więcej</h4>', false);
});

it('selects the tank from the query, derives the fish size and enforces ownership', function () {
    $fish = User::factory()->create(['fishroom_ends_at' => now()->addDay()]);
    advisorTank($fish, 'small', 'Pierwszy');
    $nano = advisorTank($fish, 'nano', 'Drugi', ['', '', '']);

    $this->actingAs($fish)->get(route('advisor.tank', ['tank' => $nano->id]))->assertSee('Zbiornik: Drugi')
        ->assertSee('Wielkość ryby dopasowana do zbiornika (Nano (do 3 cm))')
        ->assertSee('Ocenionych gatunków: 114, pominiętych: 86', false);
    $this->actingAs($fish)->get(route('advisor.tank'))->assertSee('Zbiornik: Pierwszy');

    $this->actingAs(User::factory()->create())->get(route('advisor.tank', ['tank' => $nano->id]))->assertNotFound();
    $this->actingAs($fish)->get(route('advisor.tank', ['tank' => 99999]))->assertNotFound();
});

it('sends a tank without a size band to its edit page instead of ranking', function () {
    $user = User::factory()->create();
    $tank = advisorTank($user);
    $tank->forceFill(['size_band' => null])->save();

    $this->actingAs($user)->get(route('advisor.tank'))->assertOk()
        ->assertSee('Uzupełnij wielkość zbiornika')->assertSee(route('aquarium.tank', $tank->id), false)
        ->assertDontSee('Ocenionych gatunków');
});

it('shows the description of one chosen card from the top group and nothing for others', function () {
    $user = User::factory()->create();
    $tank = advisorTank($user);

    $this->actingAs($user)->get(route('advisor.tank', ['tank' => $tank->id, 'card' => 'FN0001']))->assertSee('Gupik pawie oczko', false)->assertSee('<h4>Dowiedz się więcej</h4>', false);
    // FN0188 jest poza grupą najlepszych, więc nie odsłaniamy jego opisu
    $this->actingAs($user)->get(route('advisor.tank', ['tank' => $tank->id, 'card' => 'FN0188']))->assertDontSee('<h4>Dowiedz się więcej</h4>', false);
});

it('shows the winner with its description and the criterion that decided an equal percent', function () {
    $user = User::factory()->create();
    advisorTank($user, 'nano', 'Nano', ['medium', 'cool', 'neutral']);

    $this->actingAs($user)->get(route('advisor.tank'))
        ->assertSee('Polecany gatunek:')->assertSee('(80%)')
        ->assertSee('O wyborze decyduje temperatura.')
        ->assertSee('<h4>Dowiedz się więcej</h4>', false);
});

it('links from the tank page to the advisor for that tank', function () {
    $user = User::factory()->create();
    $tank = advisorTank($user);

    Livewire::actingAs($user)->test(TankShow::class, ['tank' => $tank->id])
        ->assertSee('Dobierz ryby do tego zbiornika')->assertSeeHtml(route('advisor.tank', ['tank' => $tank->id]));
});

it('adds the winner after acknowledging the catalogue difference and increments an existing quantity', function () {
    $user = User::factory()->create();
    $tank = advisorTank($user, 'nano', 'Nano', ['medium', 'cool', 'neutral']);
    $html = $this->actingAs($user)->get(route('advisor.tank'))->assertSee('Dodaj do obsady')->getContent();
    preg_match('/name="species" value="([^"]+)"/', $html, $m);
    $post = fn (array $data = []) => $this->actingAs($user)->post('/doradca/obsada', ['tank' => $tank->id, 'species' => $m[1]] + $data);

    $post()->assertOk()->assertSee('Uwagi przed dodaniem')->assertSee('Pasmo temperatury w katalogu');
    expect(Livestock::where('tank_id', $tank->id)->count())->toBe(0);

    $post(['acknowledge' => 1])->assertRedirect(route('advisor.tank', ['tank' => $tank->id]))->assertSessionHas('status');
    expect(Livestock::where('tank_id', $tank->id)->value('quantity'))->toBe(1);
    $post()->assertRedirect(route('advisor.tank', ['tank' => $tank->id]));
    expect(Livestock::where('tank_id', $tank->id)->value('quantity'))->toBe(2);
});

it('does not add livestock to a foreign tank', function () {
    $tank = advisorTank(User::factory()->create(), 'nano', 'Cudzy');

    $this->actingAs(User::factory()->create())->post('/doradca/obsada', ['tank' => $tank->id, 'species' => 'FN0001'])->assertNotFound();
    expect(Livestock::count())->toBe(0);
});

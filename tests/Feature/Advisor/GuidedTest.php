<?php

use App\Domain\Advisor\Engine\Option;
use App\Domain\Advisor\Engine\Question;
use App\Domain\KnowledgeBase\Actions\ApprovePhotos;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Photo;
use App\Domain\KnowledgeBase\Models\Record;
use App\Domain\Settings\SettingKey;
use App\Models\User;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Testing\TestResponse;

function advisorStep(): TestResponse
{
    app(ImportPackage::class)->handle(kbPackage());

    return test()->actingAs(aquarist())->get(route('advisor.guided'))->assertOk();
}

function answerAdvisor(TestResponse $page, string $key, string $code, array $query = []): TestResponse
{
    return test()->post(route('advisor.guided.answer', $page->viewData('params') + $query), ['key' => $key, 'code' => $code]);
}

function followAdvisor(TestResponse $response): TestResponse
{
    $response->assertStatus(303);
    $location = $response->headers->get('Location');
    expect($location)->toStartWith(route('advisor.guided').'?s=');

    return test()->get($location)->assertOk();
}

function walkAdvisor(TestResponse $page, string ...$labels): TestResponse
{
    foreach ($labels as $label) {
        $question = $page->viewData('step');
        expect($question)->toBeInstanceOf(Question::class);
        $option = array_find($question->options, fn (Option $option) => $option->label === $label);
        expect($option)->toBeInstanceOf(Option::class);
        $page->assertSee($label)->assertSeeHtml('value="'.e($option->code).'"');
        $page = followAdvisor(answerAdvisor($page, $question->key, $option->code));
    }

    return $page;
}

it('shows both unavailable paths and the quick selection to a guest', function () {
    $this->get('/doradca')->assertOk()
        ->assertSee('Krok po kroku')->assertSee('Dobierz do mojego zbiornika')
        ->assertSee('Ta funkcja wymaga zalogowania')
        ->assertSee('Szybki dobór')->assertSee('Załóż konto')->assertSeeHtml(route('drop.show'))
        ->assertDontSeeHtml(route('advisor.guided'))->assertDontSeeHtml(route('advisor.tank'));
});

it('shows guided and tank selection to both plans by default', function (bool $fishroom) {
    $this->actingAs(aquarist($fishroom))->get('/doradca')->assertOk()
        ->assertSeeHtml(route('advisor.guided'))->assertSeeHtml(route('advisor.tank'))
        ->assertDontSee('Szybki dobór')->assertDontSee('Załóż konto')->assertDontSee('Ta funkcja jest dostępna w planie Fishroom.');
})->with([false, true]);

it('omits registration and quick selection for a signed-in administrator who can use the wizard', function () {
    $this->actingAs(User::factory()->admin()->create())->get('/doradca')->assertOk()
        ->assertSeeHtml(route('advisor.guided'))->assertDontSee('Szybki dobór')->assertDontSee('Załóż konto');
});

it('explains the Fishroom requirement when guided selection is turned off for Tank', function () {
    shopSetting(SettingKey::GuidedAdvisorForTank, false);

    $this->actingAs(aquarist())->get('/doradca')->assertOk()
        ->assertSee('Krok po kroku')->assertSee('Ta funkcja jest dostępna w planie Fishroom.')
        ->assertDontSeeHtml(route('advisor.guided'))->assertSeeHtml(route('advisor.tank'));
    $this->get(route('advisor.guided'))->assertForbidden();
    $this->post(route('advisor.guided.answer'))->assertForbidden();

    $this->actingAs(aquarist(true))->get('/doradca')->assertOk()->assertSeeHtml(route('advisor.guided'));
});

it('redirects old bookmarks with their tank and card selection', function () {
    $this->get('/doradca?tank=123&card=FN0001')->assertRedirect('/doradca/moj-zbiornik?tank=123&card=FN0001');
});

it('requires login for both viewing and answering in the wizard', function () {
    $this->get('/doradca/kreator?q=neon')->assertRedirect(route('login'));
    $this->post('/doradca/kreator', ['key' => 'goal', 'code' => 'fauna'])->assertRedirect(route('login'));
});

it('reaches FN0013 through POST redirects and can keep the tank profile for another area', function () {
    $start = advisorStep()->assertSeeHtml('<progress');
    $photo = Photo::create([
        'record_id' => Record::where('legacy_id', 'FN0013')->sole()->id,
        'url' => '/storage/kb/fn0013.jpg',
        'author' => 'Autor zdjęcia',
        'licence' => 'CC BY 4.0',
    ]);

    $result = walkAdvisor(
        $start,
        'Fauna', 'Nano (< 40L)', 'Ogólne / Towarzyskie', 'Ryba', 'Dopasuj do zbiornika (zalecane)',
        'Dolna', 'Spokojny', 'Grzałka sporadycznie (18-21,5)', 'Woda kwaśna (poniżej 6.8)', 'Ameryka Południowa',
    );

    $result->assertSee('Twój wynik')->assertSee('Doradca sprawdził 2 gatunki')
        ->assertSee('94%')->assertSeeHtml('/katalog/fauna/FN0013')
        ->assertSee('89%')->assertSeeHtml('/katalog/fauna/FN0105')
        ->assertSee('Dlaczego pasuje')->assertSee('Warto rozważyć także')->assertDontSee('Jak Doradca to policzył')
        ->assertSee('Twój profil · 10 parametrów')->assertSeeHtml('loading="lazy"')
        ->assertDontSeeHtml('/storage/kb/fn0013.jpg')
        ->assertSeeInOrder(['Gdzie kupić', 'Brak ofert w sklepach w okolicy', 'Dodaj do akwarium']);

    $back = $result->viewData('catalogBackTo');
    expect($back)->toStartWith('/doradca/kreator?s=');
    app(ApprovePhotos::class)->handle(User::factory()->admin()->create(), [$photo->id]);
    $result = $this->get($back)->assertOk()
        ->assertSeeHtml('/storage/kb/fn0013.jpg')->assertSee('Autor zdjęcia · CC BY 4.0');

    foreach (['FN0013', 'FN0105'] as $id) {
        $product = route('catalog.show', ['fauna', $id, 'wroc' => $back]);
        $result->assertSeeHtml('href="'.e($product).'"');
        $this->get($product)->assertOk()->assertSee('Wróć do wyników')->assertSeeHtml('href="'.e($back).'"');
    }

    $next = $this->get($result->viewData('nextAreaUrl'))->assertOk()->assertSee('W czym mam Ci teraz pomóc?');
    $state = json_decode(Crypt::decryptString($next->viewData('params')['s']), true, flags: JSON_THROW_ON_ERROR);
    expect(array_column($state['answers'], 'key'))->toBe(['volume', 'type']);

    walkAdvisor($next, 'Flora')
        ->assertSee('Twój profil · 3 parametry')->assertSee('Nano (< 40L)')->assertSee('Ogólne / Towarzyskie');
});

it('keeps the same step after rejection and supports both kinds of back navigation', function () {
    $initial = advisorStep()->assertSee('Dobierzmy ryby do Twojego akwarium')->assertDontSee('Wstecz');
    $first = answerAdvisor($initial, 'goal', 'fauna');
    $volume = followAdvisor($first)->assertDontSee('Dobierzmy ryby do Twojego akwarium')
        ->assertSeeInOrder(['Wstecz', 'Fauna', 'Jaką wielkość akwarium bierzemy pod uwagę?']);
    $type = walkAdvisor($volume, 'Nano (< 40L)');
    $kind = walkAdvisor($type, 'Roślinne');

    $rejected = answerAdvisor($kind, 'fauna.kind', 'slimak');
    $rejected->assertRedirect(route('advisor.guided', $kind->viewData('params')));
    followAdvisor($rejected)
        ->assertSeeHtml('role="alert"')->assertSee('Wybierz jedną z dostępnych odpowiedzi.')
        ->assertSee('Twój profil · 3 parametry')->assertSee('Ryba');

    $this->get($kind->viewData('backUrl'))->assertOk()
        ->assertSee('Jaki typ akwarium bierzemy pod uwagę?')->assertSee('Twój profil · 2 parametry');
    $this->get($first->headers->get('Location'))->assertOk()
        ->assertSee('Jaką wielkość akwarium bierzemy pod uwagę?')->assertSee('Twój profil · 1 parametr');
});

it('restarts when the encrypted state is damaged or cannot be replayed', function (string $case) {
    $this->actingAs(aquarist());
    $state = $case === 'damaged' ? 'broken' : Crypt::encryptString('{"batch":null,"answers":[{"key":"type","code":"roslinne"}]}');

    $this->get(route('advisor.guided', ['s' => $state]))->assertRedirect(route('advisor.guided'));
    $this->get(route('advisor.guided'))->assertOk()
        ->assertSeeHtml('role="status"')->assertSee('Nie udało się odczytać zapisu doboru.')
        ->assertSee('W czym mam Ci teraz pomóc?');
})->with(['damaged', 'unreplayable']);

it('searches for and verifies Neon Innesa with a ranked alternative and correction', function () {
    $search = walkAdvisor(advisorStep(), 'Znajdź produkt');
    $this->get(route('advisor.guided', $search->viewData('params') + ['q' => 'ne']))->assertOk()
        ->assertSee('Wpisz dłuższą nazwę')->assertDontSee('Neon Innesa');
    $hits = $this->get(route('advisor.guided', $search->viewData('params') + ['q' => 'neon innesa']))->assertOk()
        ->assertSee('Neon Innesa')->assertSeeHtml('value="fauna:FN0011"');

    $verify = followAdvisor(answerAdvisor($hits, 'record', 'fauna:FN0011', ['q' => 'neon innesa']))
        ->assertSee('Teraz potwierdzamy warunki dla wybranego rekordu.');
    $result = walkAdvisor($verify, 'Bardzo duże (> 500L)', 'Roślinne', 'Woda obojętna (6.8 - 7.5)', 'Grzałka b. często (26-30)');
    $result->assertSee('Twój wynik')->assertSee('Słabe dopasowanie')->assertSee('17%')
        ->assertSeeHtml('<caption>Porównanie warunków w akwarium</caption>')
        ->assertSee('Twoje warunki')->assertSee('Optymalnie')->assertDontSee('Jak Doradca to policzył')
        ->assertSeeHtml('/katalog/fauna/FN0011')->assertSeeHtml('/katalog/fauna/FN0062')->assertSeeHtml('/katalog/equipment/SP0057');

    $next = $this->get($result->viewData('nextAreaUrl'))->assertOk();
    walkAdvisor($next, 'Flora')
        ->assertSee('Twój profil · 3 parametry')->assertSee('Bardzo duże (> 500L)')->assertSee('Roślinne');
});

it('shows an empty result with a way back to revise the profile', function () {
    $result = walkAdvisor(advisorStep(), 'Sprzęt', 'Nano (< 40L)', 'Biotop Twardowodny (Malawi/Tanganika)');
    $result->assertSee('Twój wynik')->assertSee('Brak dopasowań')->assertSee('Wstecz')->assertDontSeeHtml('/katalog/');
    $this->get($result->viewData('backUrl'))->assertOk()->assertSee('Jaki typ akwarium bierzemy pod uwagę?');
});

it('shows the tied proposals on equal terms and keeps links specific to their area', function (array $answers, string $area, string $first, string $second) {
    walkAdvisor(advisorStep(), ...$answers)
        ->assertSee('Równorzędne propozycje')->assertDontSee('Rekomendacja główna')
        ->assertSeeHtml("/katalog/$area/$first")->assertSeeHtml("/katalog/$area/$second")->assertSee('100%');
})->with([
    'partial tie' => [['Flora', 'Nano (< 40L)', 'Nano / Krewetkarium', 'Średnia (10-30 cm)', 'Nie wiem', 'Nie wiem', 'Nie wiem', 'Bez znaczenia'], 'flora', 'FL0080', 'FL0082'],
    'full tie' => [['Pielęgnacja', 'Małe (40L - 100L)', 'Roślinne', 'Pielęgnacja na co dzień', 'Cięcie i pielęgnacja', 'Czyszczenie szyb', 'Nie wiem', 'Nie wiem'], 'care', 'CH0001', 'CH0002'],
]);

it('explains why the leading card won the tiebreak at the same percentage', function () {
    walkAdvisor(advisorStep(), 'Sprzęt', 'Średnie (101L - 250L)', 'Ogólne / Towarzyskie', 'Filtracja', 'Filtr zewnętrzny', 'Premium', 'Eheim')
        ->assertSee('Kilka propozycji ma ten sam wynik. Polecana propozycja lepiej pasuje do typu Twojego akwarium.')
        ->assertDontSee('2 vs 1')->assertSee('Rekomendacja główna')
        ->assertSeeHtml('/katalog/equipment/SP0005')->assertSeeHtml('/katalog/equipment/SP0009');
});

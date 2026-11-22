<?php

use App\Domain\Advisor\Actions\FindProduct;
use App\Domain\Advisor\Actions\RunGuidedAdvisor;
use App\Domain\Advisor\Actions\VerifyRecord;
use App\Domain\Advisor\Engine\Answer;
use App\Domain\Advisor\Engine\CardRole;
use App\Domain\Advisor\Engine\GroupType;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Advisor\Engine\Question;
use App\Domain\Advisor\Engine\Result;
use App\Domain\Advisor\Engine\Verification;
use App\Domain\Advisor\GuidedStep;
use App\Domain\KnowledgeBase\Actions\ImportPackage;
use App\Domain\KnowledgeBase\Models\Area;

/*
 * Złote przypadki kreatora na prawdziwych plikach bazy wiedzy (tests/Fixtures/KnowledgeBase), policzone kodem referencyjnym
 * (WordPress, tryb odczytu) i porównane z nowym silnikiem na tysiącach losowych ścieżek. Różnice względem referencji
 * (flora Nano bez „Wysoka”, zero kryteriów z komunikatem, korekta zachowuje obszar, pusty wynik jako typ) mają osobne przypadki.
 */
beforeEach(fn () => app(ImportPackage::class)->handle(kbPackage()));

/**
 * Odpowiada etykietami opcji (jak kliknięcia w widoku) i zwraca ostatni krok oraz zadane pytania.
 *
 * @return array{GuidedStep, list<Question>}
 */
function guidedWalk(string ...$labels): array
{
    $run = app(RunGuidedAdvisor::class);
    $state = $run->handle();
    $asked = [];
    foreach ($labels as $label) {
        $question = $state->step;
        expect($question)->toBeInstanceOf(Question::class);
        $asked[] = $question;
        $option = array_find($question->options, fn ($o) => $o->label === $label) ?? throw new RuntimeException("{$question->key}: brak opcji „{$label}”");
        $state = $run->handle($state->answers, $state->batch, new Answer($question->key, $option->code));
        expect($state->rejection)->toBeNull();
    }

    return [$state, $asked];
}

/** @return list<string> */
function guidedLabels(Question $question): array
{
    return array_map(fn ($o) => $o->label, $question->options);
}

/** @return list<string> */
function guidedCards(Result $result): array
{
    return array_map(fn ($c) => $c->id.':'.$c->percent, $result->cards);
}

it('G01 fauna Nano / Ogólne / Ryba: every step, then FN0013 94% and FN0105 89%', function () {
    [$state, $asked] = guidedWalk(
        'Fauna', 'Nano (< 40L)', 'Ogólne / Towarzyskie', 'Ryba', 'Dopasuj do zbiornika (zalecane)',
        'Dolna', 'Spokojny', 'Grzałka sporadycznie (18-21,5)', 'Woda kwaśna (poniżej 6.8)', 'Ameryka Południowa',
    );

    expect(array_map(fn ($q) => $q->key, $asked))->toBe(['goal', 'volume', 'type', 'fauna.kind', 'fauna.size', 'fauna.zone', 'fauna.temperament', 'fauna.temperature', 'fauna.ph', 'fauna.region'])
        ->and(array_map(fn ($q) => $q->progress, $asked))->toBe([10, 20, 30, 42, 42, 42, 42, 42, 42, 42])
        ->and(guidedLabels($asked[0]))->toBe(['Fauna', 'Flora', 'Sprzęt', 'Pielęgnacja', 'Znajdź produkt'])
        ->and(guidedLabels($asked[1]))->toBe(['Nano (< 40L)', 'Małe (40L - 100L)', 'Średnie (101L - 250L)', 'Duże (251L - 500L)', 'Bardzo duże (> 500L)'])
        ->and(guidedLabels($asked[2]))->toBe(['Nano / Krewetkarium', 'Ogólne / Towarzyskie', 'Roślinne', 'Biotop Twardowodny (Malawi/Tanganika)', 'Specjalistyczne'])
        ->and(guidedLabels($asked[3]))->toBe(['Ryba', 'Skorupiak', 'Ślimak'])
        ->and(guidedLabels($asked[4]))->toBe(['Dopasuj do zbiornika (zalecane)', 'Nano (do 3 cm)', 'Mała (3-5 cm)', 'Średnia (5-10 cm)'])
        ->and(guidedLabels($asked[5]))->toBe(['Dolna', 'Środkowa', 'Górna', 'Nie wiem'])
        ->and(guidedLabels($asked[6]))->toBe(['Spokojny', 'Nie wiem'])
        ->and(guidedLabels($asked[7]))->toBe(['Grzałka sporadycznie (18-21,5)', 'Grzałka często (22-25,5)', 'Nie wiem'])
        ->and(guidedLabels($asked[8]))->toBe(['Woda kwaśna (poniżej 6.8)', 'Woda obojętna (6.8 - 7.5)', 'Nie wiem'])
        ->and(guidedLabels($asked[9]))->toBe(['Ameryka Południowa', 'Azja', 'Bez znaczenia']);

    $result = $state->step;
    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->type)->toBe(GroupType::Leader)
        ->and($result->area)->toBe(GuideArea::Fauna)->and($result->ranked)->toBe(2)->and($result->tieCount)->toBe(1)
        ->and(guidedCards($result))->toBe(['FN0013:94', 'FN0105:89'])
        ->and([$result->cards[0]->points, $result->cards[0]->maxPoints, $result->cards[1]->points])->toBe([17, 18, 16])
        ->and($result->cards[0]->scores)->toBe(['size' => 3, 'zone' => 3, 'temperament' => 3, 'temperature' => 2, 'ph' => 3, 'region' => 3])
        ->and($result->cards[0]->role)->toBe(CardRole::Main)->and($result->cards[1]->role)->toBe(CardRole::Alternative)
        ->and($result->cards[0]->fit)->toBe('Dopasowanie bardzo wysokie')
        ->and($result->cards[0]->reasons)->toBe([
            'Wielkość: pełna zgodność (Nano (do 3 cm)).', 'Strefa: pełna zgodność (Dolna).',
            'Temperament: pełna zgodność (Spokojny).', 'Temperatura: zgodność częściowa (Grzałka często (22-25,5)).',
        ])
        ->and($result->insufficient)->toBeFalse()->and($result->label)->toBe('Fauna');
});

it('G02 fauna Nano / Roślinne: the only fish still needs confirming, a disallowed snail does not change the state', function () {
    [$state, $asked] = guidedWalk('Fauna', 'Nano (< 40L)', 'Roślinne');
    $kind = $state->step;

    expect(guidedLabels($kind))->toBe(['Ryba']);

    $rejected = app(RunGuidedAdvisor::class)->handle($state->answers, $state->batch, new Answer('fauna.kind', 'slimak'));
    expect($rejected->rejection)->toBe('Wybierz jedną z dostępnych odpowiedzi.')
        ->and($rejected->answers)->toBe($state->answers)
        ->and($rejected->step)->toEqual($state->step);

    [$next] = guidedWalk('Fauna', 'Nano (< 40L)', 'Roślinne', 'Ryba');
    expect(guidedLabels($next->step))->toBe(['Dopasuj do zbiornika (zalecane)', 'Mała (3-5 cm)']);
});

it('G03 crustacean asks size, then pH, then region and nothing about zone, temperament or temperature', function () {
    [$state, $asked] = guidedWalk('Fauna', 'Nano (< 40L)', 'Nano / Krewetkarium', 'Skorupiak', 'Dopasuj do zbiornika (zalecane)');
    $keys = [];
    while ($state->step instanceof Question) {
        $keys[] = $state->step->key;
        $state = app(RunGuidedAdvisor::class)->handle($state->answers, $state->batch, new Answer($state->step->key, $state->step->options[0]->code));
    }

    expect($keys)->toBe(['fauna.ph', 'fauna.region'])
        ->and($state->step)->toBeInstanceOf(Result::class);
});

it('G04 flora Nano does not offer the tall size; the rest of the vocabulary stays fixed', function () {
    [$state, $asked] = guidedWalk('Flora', 'Nano (< 40L)', 'Nano / Krewetkarium', 'Średnia (10-30 cm)', 'Nie wiem', 'Nie wiem', 'Nie wiem', 'Bez znaczenia');

    expect(guidedLabels($asked[3]))->toBe(['B. mała (< 5 cm)', 'Niska (5-10 cm)', 'Średnia (10-30 cm)'])
        ->and(guidedLabels($asked[4]))->toBe(['Przód', 'Środek', 'Tło', 'Na dekoracje', 'Pływające', 'Nie wiem'])
        ->and(guidedLabels($asked[5]))->toBe(['Zbędny', 'Wskazany', 'Niezbędny', 'Nie wiem'])
        ->and(guidedLabels($asked[6]))->toBe(['Ciemnozielony', 'Jasnozielony', 'Czerwony/Brązowy', 'Nie wiem'])
        ->and(guidedLabels($asked[7]))->toBe(['Afryka', 'Ameryka Południowa', 'Ameryka Płn. i Środk.', 'Azja', 'Inne', 'Bez znaczenia']);

    $result = $state->step;
    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->type)->toBe(GroupType::PartialTie)->and($result->tieCount)->toBe(5)->and($result->ranked)->toBe(39)
        ->and(guidedCards($result))->toBe(['FL0080:100', 'FL0082:100', 'FL0090:100', 'FL0108:100', 'FL0155:100'])
        ->and($result->cards[0]->role)->toBe(CardRole::Tie);

    [$tall] = guidedWalk('Flora', 'Małe (40L - 100L)', 'Ogólne / Towarzyskie');
    expect(guidedLabels($tall->step))->toContain('Wysoka (>30 cm)');
});

it('G05 equipment Nano / Malawi has no area questions and ends with an empty result', function () {
    [$state] = guidedWalk('Sprzęt', 'Nano (< 40L)', 'Biotop Twardowodny (Malawi/Tanganika)');

    expect($state->step)->toBeInstanceOf(Result::class)
        ->and($state->step->type)->toBe(GroupType::Empty)
        ->and($state->step->cards)->toBe([])->and($state->step->ranked)->toBe(0)
        ->and($state->step->notice)->toContain('Brak dopasowań');
});

it('G06 equipment external filter / Medium / Ogólne / Premium / Eheim: pool 11, SP0005 12/12 decided by the tie-break', function () {
    [$state, $asked] = guidedWalk('Sprzęt', 'Średnie (101L - 250L)', 'Ogólne / Towarzyskie', 'Filtracja', 'Filtr zewnętrzny', 'Premium', 'Eheim');

    expect(guidedLabels($asked[3]))->toBe(['Filtracja', 'Oświetlenie LED', 'Ogrzewanie', 'Dozowanie CO2'])
        ->and(guidedLabels($asked[4]))->toBe(['Filtr zewnętrzny', 'Filtr wewnętrzny', 'Filtr kaskadowy'])
        ->and(guidedLabels($asked[5]))->toBe(['Standard', 'Premium', 'Nie wiem'])
        ->and(guidedLabels($asked[6]))->toBe(['Aquael', 'Eheim', 'Fluval', 'Oase', 'Nie wiem']);

    $result = $state->step;
    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->type)->toBe(GroupType::Tiebreak)->and($result->ranked)->toBe(11)
        ->and($result->label)->toBe('Sprzęt — Filtr zewnętrzny')
        ->and(guidedCards($result))->toBe(['SP0005:100', 'SP0009:100', 'SP0008:100'])
        ->and([$result->cards[0]->points, $result->cards[0]->maxPoints])->toBe([12, 12])
        ->and($result->cards[0]->reasons)->toBe([
            'Kategoria: pełna zgodność (Filtracja).', 'Rodzaj: pełna zgodność (Filtr zewnętrzny).',
            'Marka: zgodna (Premium).', 'Producent: zgodna (Eheim).',
        ])
        ->and($result->notice)->toContain('Typ akwarium (2 vs 1)');
});

it('G07 care "Czyszczenie szyb" with brand and maker skipped: CH0001–CH0003 full tie at 9/9', function () {
    [$state] = guidedWalk(
        'Pielęgnacja', 'Małe (40L - 100L)', 'Roślinne', 'Pielęgnacja na co dzień', 'Cięcie i pielęgnacja', 'Czyszczenie szyb', 'Nie wiem', 'Nie wiem',
    );

    $result = $state->step;
    expect($result)->toBeInstanceOf(Result::class)
        ->and($result->type)->toBe(GroupType::FullTie)->and($result->tieCount)->toBe(3)->and($result->ranked)->toBe(3)
        ->and(guidedCards($result))->toBe(['CH0001:100', 'CH0002:100', 'CH0003:100'])
        ->and(array_map(fn ($c) => $c->role, $result->cards))->toBe([CardRole::Tie, CardRole::Tie, CardRole::Tie])
        ->and([$result->cards[0]->points, $result->cards[0]->maxPoints])->toBe([9, 9])
        ->and($result->label)->toBe('Pielęgnacja — Cięcie i pielęgnacja');

    [$other] = guidedWalk('Pielęgnacja', 'Duże (251L - 500L)', 'Specjalistyczne', 'Pielęgnacja na co dzień', 'Cięcie i pielęgnacja', 'Czyszczenie szyb', 'Nie wiem', 'Nie wiem');
    expect(guidedCards($other->step))->toBe(guidedCards($result)); // litraż i typ nie zmieniają rankingu pielęgnacji
});

it('G08 find product: three characters, twenty hits, by name or by the full id', function () {
    $find = app(FindProduct::class);
    $ids = fn (string $phrase) => array_map(fn ($e) => $e->id, $find->handle($phrase));

    expect($ids('ne'))->toBe([])
        ->and($ids('neon innesa'))->toBe(['FN0011'])
        ->and($ids('equilibrium'))->toBe(['CH0041', 'CH0090'])
        ->and($ids('FN0011'))->toBe(['FN0011'])->and($ids('fn0011'))->toBe(['FN0011'])
        ->and($ids('FN001'))->not->toContain('FN0011')->and($ids('oase'))->toHaveCount(20);
});

it('G09 verification through the wizard: FN0001 with a one-band miss, wrong type, near pH and exact temperature gives 50% and a 100% alternative', function () {
    $run = app(RunGuidedAdvisor::class);
    [$state] = guidedWalk('Znajdź produkt');
    expect($state->step->search)->toBeTrue()->and($state->step->options)->toBe([]);

    $state = $run->handle($state->answers, $state->batch, new Answer('record', 'fauna:FN0001'));
    expect($state->step->key)->toBe('verify.volume')
        ->and($state->step->intro)->toBe('Teraz potwierdzamy warunki dla wybranego rekordu.')
        ->and($state->step->text)->toContain('Optymalnie dla tego rekordu: ');

    foreach (['verify.volume' => 'nano-40l', 'verify.type' => 'nano-krewetkarium', 'verify.ph' => 'woda-kwasna-ponizej-6-8', 'verify.temperature' => 'grzalka-czesto-22-25-5'] as $key => $code) {
        expect($state->step->key)->toBe($key);
        $state = $run->handle($state->answers, $state->batch, new Answer($key, $code));
        expect($state->rejection)->toBeNull();
    }

    $v = $state->step;
    expect($v)->toBeInstanceOf(Verification::class)
        ->and([$v->id, $v->area, $v->percent, $v->verdict])->toBe(['FN0001', GuideArea::Fauna, 50, 'Słabe dopasowanie'])
        ->and(array_column($v->compatibility, 'status'))->toBe(['wymaga uwagi', 'niezgodne', 'częściowo zgodne', 'zgodne'])
        ->and($v->warnings)->toBe(['Litraż jest bliski zakresowi optymalnemu, ale nie mieści się idealnie.', 'Typ akwarium nie odpowiada profilowi tego rekordu.'])
        ->and([$v->alternative->id, $v->alternative->percent, $v->alternative->role])->toBe(['FN0013', 100, CardRole::Better])
        ->and($v->correction)->toBeNull()
        ->and(array_map(fn ($r) => $r->area, $v->supporting))->toBe([GuideArea::Care, GuideArea::Care]);
});

it('G10 verification below the threshold: FN0011 at 17% gets the alternative and a correction that keeps its own area', function () {
    $verify = app(VerifyRecord::class);
    $answers = [
        new Answer('verify.volume', 'bardzo-duze-500l'), new Answer('verify.type', 'roslinne'),
        new Answer('verify.ph', 'woda-obojetna-6-8-7-5'), new Answer('verify.temperature', 'grzalka-b-czesto-26-30'),
    ];

    $v = $verify->handle(Area::Fauna, 'FN0011', $answers)->step;
    expect($v)->toBeInstanceOf(Verification::class)
        ->and([$v->percent, $v->verdict])->toBe([17, 'Słabe dopasowanie'])
        ->and([$v->alternative->id, $v->alternative->percent])->toBe(['FN0062', 92])
        ->and([$v->correction->id, $v->correction->area, $v->correction->percent, $v->correction->role])->toBe(['SP0057', GuideArea::Equipment, 90, CardRole::Correction]);

    expect($verify->handle(Area::Fauna, 'FN9999'))->toBeNull();
});

it('G11 verification without questions: CH0050 ends at once, with a note instead of a bare 100%', function () {
    $v = app(VerifyRecord::class)->handle(Area::Care, 'CH0050')->step;

    expect($v)->toBeInstanceOf(Verification::class)
        ->and([$v->percent, $v->verdict, $v->compatibility])->toBe([100, 'Zgodny', []])
        ->and($v->notice)->toContain('Za mało danych do porównania');
});

it('G12 a result can be replayed on the pinned batch after a new catalog is published', function () {
    $dir = kbPackage();
    $path = $dir.DIRECTORY_SEPARATOR.'baza_fauna_end1.txt.php';
    file_put_contents($path, str_replace('Neon Innesa', 'Neon Zmieniony', file_get_contents($path)));

    [$first] = guidedWalk('Fauna', 'Nano (< 40L)', 'Ogólne / Towarzyskie', 'Ryba');
    $pinned = $first->batch;
    app(ImportPackage::class)->handle($dir);

    $run = app(RunGuidedAdvisor::class);
    expect(app(FindProduct::class)->handle('neon innesa'))->toBe([])
        ->and(array_map(fn ($e) => $e->id, app(FindProduct::class)->handle('neon innesa', $pinned)))->toBe(['FN0011'])
        ->and($run->handle($first->answers, $pinned)->batch)->toBe($pinned)
        ->and($run->handle($first->answers)->batch)->not->toBe($pinned)
        ->and($run->handle($first->answers, $pinned)->step)->toEqual($first->step);
});

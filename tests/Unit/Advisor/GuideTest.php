<?php

use App\Domain\Advisor\Engine\Answer;
use App\Domain\Advisor\Engine\Context;
use App\Domain\Advisor\Engine\GroupType;
use App\Domain\Advisor\Engine\Guide;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Advisor\Engine\InvalidAnswers;
use App\Domain\Advisor\Engine\Item;
use App\Domain\Advisor\Engine\Question;
use App\Domain\Advisor\Engine\Result;
use App\Domain\Advisor\Engine\Rules;
use App\Domain\Advisor\Engine\Scoring;
use App\Domain\Advisor\Engine\Verification;

/** Reguły z domyślnych wartości ustawień (silnik ich nie zna, więc test podaje je jawnie). */
function guideRules(array $verification = [70, 90]): Rules
{
    return new Rules([50, 70, 85], $verification, 3, 5, true, [
        'ph_acid' => ['acid buffer', 'ph control'], 'ph_other' => ['equilibrium'], 'co2' => ['co2 plus'], 'heating_category' => 'Ogrzewanie', 'percent' => 90,
    ]);
}

/** Rekord syntetyczny z sensownymi wartościami domyślnymi dla obszaru. @param array<string, string> $fields */
function guideItem(GuideArea $area, string $id, array $fields = []): Item
{
    $defaults = match ($area) {
        GuideArea::Fauna => [
            'Typ' => 'Ryba', 'Wielkość' => 'Mała (3-5 cm)', 'Temperatura' => 'Grzałka często (22-25,5)', 'pH' => 'Woda obojętna (6.8 - 7.5)',
            'Temperament' => 'Spokojny', 'Strefa' => 'Dolna', 'Region' => 'Azja', 'WielkośćAkwarium' => 'Nano (< 40L);Małe (40L - 100L)', 'TypAkwarium' => 'Ogólne / Towarzyskie',
        ],
        GuideArea::Equipment => [
            'kategoria_glowna' => 'Filtracja', 'rodzaj' => 'Filtr zewnętrzny', 'producent' => 'Aquael', 'Marka' => 'Standard', 'produkt' => $id,
            'WielkośćAkwarium' => 'Nano (< 40L)', 'TypAkwarium' => 'Ogólne / Towarzyskie',
        ],
        GuideArea::Care => [
            'kategoria_zadania' => 'Start / przygotowanie', 'rodzaj_zadania' => 'Przygotowanie wody', 'obszar' => 'Biostartery',
            'producent' => 'JBL', 'Marka' => 'Premium', 'produkt' => $id, 'opis' => '',
        ],
        GuideArea::Flora => ['Wielkość' => 'Niska (5-10 cm)', 'WielkośćAkwarium' => 'Nano (< 40L)', 'TypAkwarium' => 'Ogólne / Towarzyskie'],
    };

    return new Item($area, $id, $id, null, $fields + $defaults);
}

/**
 * Odpowiada etykietami przez `submit` (jak widok) i zwraca listę odpowiedzi oraz ostatni krok.
 *
 * @return array{list<Answer>, Question|Result|Verification}
 */
function guideWalk(Guide $guide, string ...$labels): array
{
    $answers = [];
    foreach ($labels as $label) {
        $question = $guide->next($answers);
        expect($question)->toBeInstanceOf(Question::class);
        $option = array_find($question->options, fn ($o) => $o->label === $label) ?? throw new RuntimeException("{$question->key}: brak opcji „{$label}”");
        $submission = $guide->submit($answers, new Answer($question->key, $option->code));
        expect($submission->accepted)->toBeTrue();
        $answers = $submission->answers;
    }

    return [$answers, $guide->next($answers)];
}

/** @return list<string> */
function guideOptions(Question $question): array
{
    return array_map(fn ($o) => $o->label, $question->options);
}

it('scores distance 0/1/2/3+ as 3/2/1/0 and treats a value off the scale as 0', function () {
    $scale = ['a', 'b', 'c', 'd'];

    expect(array_map(fn ($v) => Scoring::ordinal('a', $v, $scale), ['a', 'b', 'c', 'd']))->toBe([3, 2, 1, 0])
        ->and(Scoring::ordinal('d', 'b', $scale))->toBe(1)
        ->and(Scoring::ordinal('a', 'x', $scale))->toBe(0)
        ->and(Scoring::ordinal('x', 'a', $scale))->toBe(0);
});

it('scores exact, binary and multi-valued columns', function () {
    $fauna = guideItem(GuideArea::Fauna, 'F', ['Region' => 'Azja', 'pH' => 'Woda kwaśna (poniżej 6.8);Woda obojętna (6.8 - 7.5)']);
    $care = guideItem(GuideArea::Care, 'C', ['producent' => 'JBL', 'Marka' => 'Premium']);

    expect(Scoring::column(GuideArea::Fauna, 'Region', 'Azja', $fauna))->toBe(3)
        ->and(Scoring::column(GuideArea::Fauna, 'Region', 'Afryka', $fauna))->toBe(0) // region: zgodny 3, inny 0
        ->and(Scoring::column(GuideArea::Fauna, 'pH', 'Woda obojętna (6.8 - 7.5)', $fauna))->toBe(3) // najlepsza z wartości rekordu
        ->and(Scoring::column(GuideArea::Fauna, 'pH', 'Woda zasadowa (powyżej 7.5)', $fauna))->toBe(2)
        ->and(Scoring::column(GuideArea::Care, 'producent', 'JBL', $care))->toBe(3)
        ->and(Scoring::column(GuideArea::Care, 'producent', 'Tetra', $care))->toBe(2) // marka i producent: inny 2, nigdy 1
        ->and(Scoring::column(GuideArea::Care, 'Marka', 'Standard', $care))->toBe(2)
        ->and(Scoring::column(GuideArea::Fauna, 'Strefa', 'Dolna', guideItem(GuideArea::Fauna, 'E', ['Strefa' => ''])))->toBe(0);
});

it('applies the fauna funnel: keeps scores above 0 only when someone scores 2 or more', function () {
    $guide = new Guide([], guideRules());
    $funnel = new ReflectionMethod(Guide::class, 'funnel');
    $ids = fn (array $pool, array $answers) => array_map(fn (Item $i) => $i->id, $funnel->invoke($guide, $pool, $answers));
    $temp = fn (string $id, string $band) => guideItem(GuideArea::Fauna, $id, ['Temperatura' => $band]);
    $answers = ['Typ' => 'Ryba', 'Temperatura' => 'Zimnolubne (poniżej 18)'];

    // oceny [2, 1, 0]: zostają dwa pierwsze
    expect($ids([$temp('A', 'Grzałka sporadycznie (18-21,5)'), $temp('B', 'Grzałka często (22-25,5)'), $temp('C', 'Grzałka b. często (26-30)')], $answers))->toBe(['A', 'B'])
        // oceny [1, 0]: nikt nie ma 2, pula bez zmian
        ->and($ids([$temp('B', 'Grzałka często (22-25,5)'), $temp('C', 'Grzałka b. często (26-30)')], $answers))->toBe(['B', 'C'])
        // „Nie wiem” i wielkość (tylko punktuje) nigdy nie zawężają
        ->and($ids([$temp('A', 'Zimnolubne (poniżej 18)'), $temp('C', 'Grzałka b. często (26-30)')], ['Typ' => 'Ryba', 'Temperatura' => 'Nie wiem', 'Wielkość' => 'Nano (do 3 cm)']))->toBe(['A', 'C']);
});

it('keeps a tank-size gap of three bands out, accepts "Wszystkie", rejects an empty size and ignores a missing column', function () {
    $items = [
        guideItem(GuideArea::Fauna, 'OK', ['Wielkość' => 'Średnia (5-10 cm)']),
        guideItem(GuideArea::Fauna, 'BIG', ['Wielkość' => 'Duża (10-15 cm)']), // Nano + 3 pasma: odpada
        guideItem(GuideArea::Fauna, 'ALL', ['WielkośćAkwarium' => 'Wszystkie']),
        guideItem(GuideArea::Fauna, 'EMPTY', ['WielkośćAkwarium' => '']),
        guideItem(GuideArea::Fauna, 'LARGE', ['WielkośćAkwarium' => 'Duże (251L - 500L)']), // fauna: pasmo większe niż wybrane odpada
        new Item(GuideArea::Fauna, 'NOCOLUMN', 'n', null, ['Typ' => 'Ryba']), // brak kolumny pomija jej filtr
    ];
    $pool = new ReflectionMethod(Guide::class, 'pool');
    $context = tap(new Context, function (Context $c) {
        [$c->area, $c->volume] = [GuideArea::Fauna, 'Nano (< 40L)'];
    });

    $ids = array_map(fn (Item $i) => $i->id, $pool->invoke(new Guide($items, guideRules()), $context, []));

    expect($ids)->toBe(['OK', 'ALL', 'NOCOLUMN']);
});

it('asks only 2–8 option questions and confirms a lone fauna type', function () {
    $care = fn (string $id, string $maker) => guideItem(GuideArea::Care, $id, ['producent' => $maker]);
    $makers = array_map(fn ($n) => $care("C$n", "M$n"), range(1, 9));
    $guide = new Guide([guideItem(GuideArea::Fauna, 'F1'), ...$makers], guideRules());

    // jedyna wartość nie jest pytaniem (kategoria, działanie, zastosowanie), marka to „Premium” + „Nie wiem”, 9 producentów to za dużo
    [, $step] = guideWalk($guide, 'Pielęgnacja', 'Nano (< 40L)', 'Ogólne / Towarzyskie');
    expect($step)->toBeInstanceOf(Question::class)->and($step->key)->toBe('care.brand')->and(guideOptions($step))->toBe(['Premium', 'Nie wiem']);

    [, $result] = guideWalk($guide, 'Pielęgnacja', 'Nano (< 40L)', 'Ogólne / Towarzyskie', 'Premium');
    expect($result)->toBeInstanceOf(Result::class)->and($result->ranked)->toBe(9)->and($result->criteria)->toBe(['Marka']);

    // jedyny typ fauny trzeba potwierdzić
    [, $kind] = guideWalk($guide, 'Fauna', 'Nano (< 40L)', 'Ogólne / Towarzyskie');
    expect($kind->key)->toBe('fauna.kind')->and(guideOptions($kind))->toBe(['Ryba']);
});

it('shows ex aequo for 2 criteria and lets the technical tie-break pick a leader for 3', function () {
    $items = [
        guideItem(GuideArea::Equipment, 'EQ1', ['WielkośćAkwarium' => 'Nano (< 40L)']),
        guideItem(GuideArea::Equipment, 'EQ2', ['WielkośćAkwarium' => 'Nano (< 40L);Małe (40L - 100L)']),
        guideItem(GuideArea::Equipment, 'EQ3', ['producent' => 'Eheim', 'Marka' => 'Premium']),
    ];
    $guide = new Guide($items, guideRules());
    $start = ['Sprzęt', 'Nano (< 40L)', 'Ogólne / Towarzyskie'];

    // rodzaj jest jeden → pominięty; marka i producent = 2 kryteria; EQ1 i EQ2 mają ten sam wynik widoczny
    [, $two] = guideWalk($guide, ...$start, ...['Standard', 'Aquael']);
    expect($two->type)->toBe(GroupType::PartialTie)->and($two->tieCount)->toBe(2)
        ->and(array_map(fn ($c) => $c->id, $two->cards))->toBe(['EQ1', 'EQ2', 'EQ3'])
        ->and($two->cards[2]->percent)->toBe(67)->and($two->notice)->toContain('nie wskazuje jednego zwycięzcy');

    // 1 kryterium: tylko producent
    [, $one] = guideWalk($guide, ...$start, ...['Nie wiem', 'Aquael']);
    expect($one->criteria)->toBe(['Producent'])->and($one->cards[0]->percent)->toBe(100)->and($one->cards[2]->percent)->toBe(67)->and($one->cards[2]->id)->toBe('EQ3');
});

it('reports "not enough data" instead of a bare 100% when no criterion is active', function () {
    $items = [guideItem(GuideArea::Equipment, 'EQ1'), guideItem(GuideArea::Equipment, 'EQ2'), guideItem(GuideArea::Equipment, 'EQ3'), guideItem(GuideArea::Equipment, 'EQ4')];
    $guide = new Guide($items, guideRules());

    [, $none] = guideWalk($guide, 'Sprzęt', 'Nano (< 40L)', 'Ogólne / Towarzyskie', 'Nie wiem', 'Nie wiem');

    expect($none)->toBeInstanceOf(Result::class)->and($none->insufficient)->toBeTrue()->and($none->criteria)->toBe([])
        ->and($none->notice)->toStartWith('Za mało danych do porównania')
        ->and($none->ranked)->toBe(4)->and($none->tieCount)->toBe(4)->and($none->type)->toBe(GroupType::FullTie)
        ->and($none->cards)->toHaveCount(3)->and($none->shortlist)->toHaveCount(4); // karty wg polityki, lista rezerwowa pełna
});

it('breaks a 3-criteria tie with the technical tie-break and says so', function () {
    $items = [
        guideItem(GuideArea::Equipment, 'EQ1', ['WielkośćAkwarium' => 'Nano (< 40L)']),
        guideItem(GuideArea::Equipment, 'EQ2', ['WielkośćAkwarium' => 'Nano (< 40L);Małe (40L - 100L)']),
        guideItem(GuideArea::Equipment, 'EQ3', ['rodzaj' => 'Filtr wewnętrzny', 'producent' => 'Eheim']),
    ];
    $guide = new Guide($items, guideRules());

    [, $three] = guideWalk($guide, 'Sprzęt', 'Nano (< 40L)', 'Ogólne / Towarzyskie', 'Filtr zewnętrzny', 'Standard', 'Aquael');

    expect($three->criteria)->toBe(['Rodzaj', 'Marka', 'Producent'])
        ->and($three->type)->toBe(GroupType::Tiebreak)->and($three->tieCount)->toBe(1)
        ->and(array_map(fn ($c) => $c->id, $three->cards))->toBe(['EQ1', 'EQ2']) // EQ3 odpada na twardym filtrze rodzaju
        ->and($three->notice)->toContain('Wielkość akwarium (3 vs 2)');
});

it('rejects an unknown option, an answer for another question and answers after the end, leaving the state untouched', function () {
    $guide = new Guide([guideItem(GuideArea::Fauna, 'F1'), guideItem(GuideArea::Fauna, 'F2', ['Typ' => 'Skorupiak'])], guideRules());
    [$answers] = guideWalk($guide, 'Fauna');

    $unknown = $guide->submit($answers, new Answer('volume', 'nie-ma'));
    $other = $guide->submit($answers, new Answer('type', 'ogolne-towarzyskie'));

    expect($unknown->accepted)->toBeFalse()->and($unknown->message)->toBe('Wybierz jedną z dostępnych odpowiedzi.')
        ->and($unknown->answers)->toBe($answers)->and($unknown->step)->toEqual($guide->next($answers))
        ->and($other->message)->toBe('Ta odpowiedź nie dotyczy bieżącego pytania.');

    [$done, $result] = guideWalk($guide, 'Fauna', 'Nano (< 40L)', 'Ogólne / Towarzyskie', 'Ryba');
    while (($step = $guide->next($done)) instanceof Question) {
        $done = $guide->submit($done, new Answer($step->key, $step->options[0]->code))->answers;
    }
    expect($guide->submit($done, new Answer('goal', 'fauna'))->message)->toBe('Dobór jest już zakończony. Zacznij od nowa.');
});

it('rejects an area answer that would leave the pool empty, but never for flora (it only scores)', function () {
    $guide = new Guide([guideItem(GuideArea::Fauna, 'F1')], guideRules());
    $empties = new ReflectionMethod(Guide::class, 'emptiesPool');
    $context = fn (GuideArea $area, string $type) => tap(new Context, function (Context $c) use ($area, $type) {
        [$c->area, $c->volume, $c->type] = [$area, 'Nano (< 40L)', $type];
    });
    $kind = new Question('fauna.kind', '?', [], 42);

    expect($empties->invoke($guide, $kind, $context(GuideArea::Fauna, 'Ogólne / Towarzyskie')))->toBeFalse()
        ->and($empties->invoke($guide, $kind, $context(GuideArea::Fauna, 'Roślinne')))->toBeTrue()
        ->and($empties->invoke($guide, new Question('flora.zone', '?', [], 42), $context(GuideArea::Flora, 'Roślinne')))->toBeFalse()
        ->and($empties->invoke($guide, new Question('type', '?', [], 30), $context(GuideArea::Fauna, 'Roślinne')))->toBeFalse();
});

it('is deterministic and going back is dropping the last answer', function () {
    $guide = new Guide([guideItem(GuideArea::Fauna, 'F1'), guideItem(GuideArea::Fauna, 'F2', ['Temperament' => 'Umiarkowany'])], guideRules());
    [$answers] = guideWalk($guide, 'Fauna', 'Nano (< 40L)', 'Ogólne / Towarzyskie', 'Ryba');
    $before = $guide->next(array_slice($answers, 0, -1));

    expect($guide->next($answers))->toEqual($guide->next($answers))
        ->and($before)->toBeInstanceOf(Question::class)->and($before->key)->toBe('fauna.kind')
        ->and($guide->next([...array_slice($answers, 0, -1), $answers[3]]))->toEqual($guide->next($answers));

    expect(fn () => $guide->next([new Answer('volume', 'nano-40l')]))->toThrow(InvalidAnswers::class);
});

it('verifies with configurable thresholds and picks the first correction in the order pH, temperature, CO2', function () {
    $items = [
        guideItem(GuideArea::Fauna, 'F1', ['pH' => 'Woda kwaśna (poniżej 6.8)', 'Temperatura' => 'Grzałka sporadycznie (18-21,5)']),
        guideItem(GuideArea::Fauna, 'F2', ['pH' => 'Woda zasadowa (powyżej 7.5)', 'Temperatura' => 'Grzałka b. często (26-30)', 'TypAkwarium' => 'Roślinne']),
        guideItem(GuideArea::Equipment, 'HEAT', ['kategoria_glowna' => 'Ogrzewanie', 'rodzaj' => 'Grzałka wewnętrzna', 'WielkośćAkwarium' => 'Duże (251L - 500L)']),
        guideItem(GuideArea::Equipment, 'HEAT2', ['kategoria_glowna' => 'Ogrzewanie', 'rodzaj' => 'Grzałka wewnętrzna', 'WielkośćAkwarium' => 'Nano (< 40L)']),
        guideItem(GuideArea::Care, 'BUF', ['produkt' => 'Acid Buffer Pro', 'obszar' => 'Uzdatniacze wody']),
        guideItem(GuideArea::Care, 'EQ', ['produkt' => 'Equilibrium']),
    ];
    $record = $items[0];
    $answer = fn (string $key, string $code) => new Answer("verify.$key", $code);
    $good = [$answer('volume', 'nano-40l'), $answer('type', 'ogolne-towarzyskie')];

    $acid = (new Guide($items, guideRules()))->verify($record, [...$good, $answer('ph', 'woda-zasadowa-powyzej-7-5'), $answer('temperature', 'grzalka-b-czesto-26-30')]);
    expect($acid)->toBeInstanceOf(Verification::class)->and($acid->percent)->toBe(50)
        ->and($acid->correction->id)->toBe('BUF')->and($acid->correction->area)->toBe(GuideArea::Care); // pH przed temperaturą

    $heat = (new Guide($items, guideRules()))->verify($record, [...$good, $answer('ph', 'woda-kwasna-ponizej-6-8'), $answer('temperature', 'grzalka-b-czesto-26-30')]);
    expect($heat->percent)->toBe(75)->and($heat->verdict)->toBe('Zgodny warunkowo')->and($heat->correction)->toBeNull(); // 75% ≥ 70: bez korekty

    $low = [$answer('volume', 'duze-251l-500l'), $answer('type', 'roslinne'), $answer('ph', 'woda-kwasna-ponizej-6-8'), $answer('temperature', 'grzalka-b-czesto-26-30')];
    $heating = (new Guide($items, guideRules()))->verify($record, $low);
    expect($heating->percent)->toBe(25)->and($heating->correction->id)->toBe('HEAT')->and($heating->correction->area)->toBe(GuideArea::Equipment);

    // własne progi: 40 / 70 zamiast 70 / 90
    $custom = (new Guide($items, guideRules([40, 70])))->verify($record, [...$good, $answer('ph', 'woda-zasadowa-powyzej-7-5'), $answer('temperature', 'grzalka-b-czesto-26-30')]);
    expect($custom->verdict)->toBe('Zgodny warunkowo')->and($custom->correction)->toBeNull();
});

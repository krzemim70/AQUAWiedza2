<?php

namespace App\Domain\Advisor\Engine;

use LogicException;

/**
 * Kreator „jedno pytanie na raz”. Stan to czysta lista odpowiedzi: każde wywołanie odtwarza ścieżkę od początku, więc wynik
 * jest deterministyczny, a cofnięcie to usunięcie ostatniej odpowiedzi.
 *
 * Ścieżka: cel → litraż → typ akwarium → pytania obszaru (kolejność z {@see Vocabulary::plan()}) → wynik; albo „Znajdź produkt”:
 * wyszukanie rekordu → pytania weryfikacji → werdykt. Pytanie z liczbą opcji spoza 2–8 jest pomijane bez zapisu odpowiedzi
 * (wyjątek: jedyny typ fauny trzeba potwierdzić).
 */
final class Guide
{
    private const GOALS = ['fauna' => 'Fauna', 'flora' => 'Flora', 'equipment' => 'Sprzęt', 'care' => 'Pielęgnacja', 'find' => 'Znajdź produkt'];

    private const MAX_OPTIONS = 8;

    private Records $records;

    private Verifier $verifier;

    /** @param list<Item> $items */
    public function __construct(array $items, private Rules $rules)
    {
        $this->records = new Records($items);
        $this->verifier = new Verifier($this->records, $rules);
    }

    /**
     * Następne pytanie albo wynik dla listy odpowiedzi.
     *
     * @param  list<Answer>  $answers
     *
     * @throws InvalidAnswers gdy odpowiedzi nie odtwarzają się na tych danych
     */
    public function next(array $answers): Question|Result|Verification
    {
        return $this->run($answers)[1];
    }

    /**
     * @param  list<Answer>  $answers
     * @return array{Question|Result|Verification, list<Question>}
     */
    public function review(array $answers): array
    {
        [, $step, $questions] = $this->run($answers);

        return [$step, $questions];
    }

    /**
     * Weryfikacja wskazanego rekordu (bez kwalifikacji i wyszukiwania): pytania o warunki albo werdykt.
     *
     * @param  list<Answer>  $answers
     */
    public function verify(Item $record, array $answers): Question|Verification
    {
        $step = $this->run($answers, $record)[1];

        return $step instanceof Result ? throw new LogicException('Weryfikacja nie daje rankingu.') : $step;
    }

    /**
     * Dodaje odpowiedź do listy. Odpowiedź spoza bieżących opcji, do innego pytania albo zawężająca pulę do zera jest
     * odrzucana z komunikatem, a lista zostaje bez zmian.
     *
     * @param  list<Answer>  $answers
     */
    public function submit(array $answers, Answer $answer): Submission
    {
        [, $step] = $this->run($answers);
        $reject = fn (string $message) => new Submission(false, $message, $answers, $step);
        if (! $step instanceof Question) {
            return $reject('Dobór jest już zakończony. Zacznij od nowa.');
        }
        if ($answer->key !== $step->key) {
            return $reject('Ta odpowiedź nie dotyczy bieżącego pytania.');
        }
        $known = $step->search ? $this->records->fromCode($answer->code) !== null : array_find($step->options, fn (Option $o) => $o->code === $answer->code) !== null;
        if (! $known) {
            return $reject('Wybierz jedną z dostępnych odpowiedzi.');
        }

        $next = [...$answers, $answer];
        [$context, $after] = $this->run($next);
        if ($this->emptiesPool($step, $context)) {
            return $reject('To zawężenie nie daje żadnych wyników. Wybierz inną odpowiedź.');
        }

        return new Submission(true, null, $next, $after);
    }

    /** Odpowiedź obszarowa zostawiająca pustą pulę (flora: odpowiedzi tylko punktują, więc nie odrzucamy). */
    private function emptiesPool(Question $question, Context $context): bool
    {
        $column = Vocabulary::column($question->key);

        return $column !== null && $context->area !== null && ! $context->find && $context->area !== GuideArea::Flora
            && $this->pool($context, $context->answers) === [];
    }

    /**
     * @param  list<Answer>  $answers
     * @return array{Context, Question|Result|Verification, list<Question>}
     */
    private function run(array $answers, ?Item $record = null): array
    {
        $context = new Context;
        if ($record !== null) {
            [$context->find, $context->record, $context->area] = [true, $record, $record->area];
        }
        $questions = [];
        foreach ($answers as $answer) {
            $question = $this->ask($context) ?? throw new InvalidAnswers('Za dużo odpowiedzi.');
            if ($answer->key !== $question->key) {
                throw new InvalidAnswers("Oczekiwano odpowiedzi na „{$question->key}”, jest „{$answer->key}”.");
            }
            $this->apply($context, $question, $answer);
            $questions[] = $question;
        }

        return [$context, $this->ask($context) ?? $this->finish($context), $questions];
    }

    private function apply(Context $context, Question $question, Answer $answer): void
    {
        if ($question->search) {
            $record = $this->records->fromCode($answer->code) ?? throw new InvalidAnswers("Nie ma rekordu „{$answer->code}”.");
            [$context->record, $context->area] = [$record, $record->area];

            return;
        }
        $option = array_find($question->options, fn (Option $o) => $o->code === $answer->code) ?? throw new InvalidAnswers("Nieznany kod „{$answer->code}”.");

        if ($question->key === 'goal') {
            if ($option->code === 'find') {
                $context->find = true;
            } else {
                $context->area = GuideArea::from($option->code);
            }
        } elseif ($question->key === 'volume') {
            $context->volume = $option->label;
        } elseif ($question->key === 'type') {
            $context->type = $option->label;
        } else {
            $column = Vocabulary::column($question->key) ?? throw new InvalidAnswers('Nieznane pytanie.');
            $context->answers[$column] = match ($option->code) {
                'unknown' => Vocabulary::UNKNOWN,
                'fit' => FishBand::matching(TankBand::fromLabel((string) $context->volume) ?? TankBand::Small)->label(),
                default => $option->label,
            };
        }
    }

    /** Następne pytanie albo null, gdy dobór jest gotowy do wyniku. */
    private function ask(Context $context): ?Question
    {
        if ($context->area === null && ! $context->find) {
            return new Question('goal', 'W czym mam Ci teraz pomóc?', array_map(fn (string $code, string $label) => new Option($code, $label), array_keys(self::GOALS), self::GOALS), 10);
        }
        if ($context->find) {
            return $context->record === null
                ? new Question('record', 'Który produkt chcesz sprawdzić?', [], 20, null, null, true)
                : $this->verifier->question($context->record, $context->answers);
        }
        $area = $context->area ?? throw new LogicException('Brak obszaru.');
        if ($context->volume === null) {
            return new Question('volume', 'Jaką wielkość akwarium bierzemy pod uwagę?', Vocabulary::options($this->records->tankSizes()), 20);
        }
        if ($context->type === null) {
            $areas = $area === GuideArea::Care ? [GuideArea::Fauna, GuideArea::Flora, GuideArea::Equipment] : [$area];

            return new Question('type', 'Jaki typ akwarium bierzemy pod uwagę?', Vocabulary::options($this->records->types($areas)), 30);
        }

        return $this->areaQuestion($context, $area);
    }

    private function areaQuestion(Context $context, GuideArea $area): ?Question
    {
        $pool = $this->pool($context, $context->answers);
        if ($pool === []) {
            return null;
        }
        $columns = Vocabulary::columns($area);
        foreach (Vocabulary::plan($area, $context->answers['Typ'] ?? null) as $column) {
            if (isset($context->answers[$column])) {
                continue;
            }
            $labels = $this->labels($context, $pool, $column);
            $confirm = $area === GuideArea::Fauna && $column === 'Typ' && count($labels) === 1;
            if (! $confirm && (count($labels) <= 1 || count($labels) > self::MAX_OPTIONS)) {
                continue;
            }

            return new Question("{$area->value}.{$columns[$column]['key']}", $columns[$column]['question'], Vocabulary::options($labels), 42, $this->label($area, $context->answers));
        }

        return null;
    }

    /**
     * Etykiety opcji z bieżącej puli. Każda opcja musi zostawiać niepustą pulę; „Nie wiem” (dla regionu „Bez znaczenia”)
     * dopisujemy przy kryteriach opcjonalnych, a przy wielkości fauny na początek „Dopasuj do zbiornika”.
     *
     * @param  list<Item>  $pool
     * @return list<string>
     */
    private function labels(Context $context, array $pool, string $column): array
    {
        $area = $context->area ?? throw new LogicException('Brak obszaru.');
        $spec = Vocabulary::columns($area)[$column];
        if ($spec['fixed']) {
            $labels = $spec['order'];
            if ($area === GuideArea::Flora && $column === 'Wielkość' && ($context->type === Vocabulary::NANO_TYPE || $context->volume === TankBand::Nano->label())) {
                $labels = array_values(array_diff($labels, [end($spec['order'])]));
            }
        } else {
            $values = Records::distinct($pool, $column);
            if ($column === 'Typ') {
                $values = array_values(array_intersect($values, Vocabulary::KINDS));
            }
            $values = array_filter($values, fn (string $v) => $this->pool($context, [...$context->answers, $column => $v]) !== []);
            $labels = Vocabulary::order(array_values($values), $spec['order']);
        }
        if ($spec['optional'] && $labels !== []) {
            $labels[] = $spec['any'] ? Vocabulary::ANY : Vocabulary::UNKNOWN;
        }
        if ($area === GuideArea::Fauna && $column === 'Wielkość' && $labels !== []) {
            array_unshift($labels, Vocabulary::FIT);
        }

        return $labels;
    }

    /**
     * Podpis grupy: obszar, przy sprzęcie i pielęgnacji doprecyzowany wybranym rodzajem lub kategorią.
     *
     * @param  array<string, string>  $answers
     */
    private function label(GuideArea $area, array $answers): string
    {
        [$kind, $category] = match ($area) {
            GuideArea::Equipment => ['rodzaj', 'kategoria_glowna'],
            GuideArea::Care => ['rodzaj_zadania', 'kategoria_zadania'],
            default => [null, null],
        };
        $detail = $kind === null ? '' : ($answers[$kind] ?? $answers[$category] ?? '');

        return $detail === '' ? $area->label() : "{$area->label()} — $detail";
    }

    /**
     * Pula rekordów po filtrach twardych: litraż i typ akwarium, odpowiedzi zawężające (typ fauny, kategorie sprzętu i pielęgnacji)
     * oraz lejek fauny.
     *
     * @param  array<string, string>  $answers
     * @return list<Item>
     */
    private function pool(Context $context, array $answers): array
    {
        $area = $context->area ?? throw new LogicException('Brak obszaru.');
        $pool = array_values(array_filter($this->records->of($area), function (Item $item) use ($context, $answers, $area) {
            if (! $this->passesTank($item, $context)) {
                return false;
            }
            foreach (Vocabulary::hard($area) as $column) {
                if (($answers[$column] ?? '') !== '' && ! in_array($answers[$column], $item->tokens($column), true)) {
                    return false;
                }
            }

            return true;
        }));

        return $area === GuideArea::Fauna ? $this->funnel($pool, $answers) : $pool;
    }

    /** Litraż i typ akwarium wybrane na początku; ryba o co najmniej 3 pasma większa od zbiornika odpada. */
    private function passesTank(Item $item, Context $context): bool
    {
        $tank = $context->volume === null ? null : TankBand::fromLabel($context->volume);
        if ($context->area === GuideArea::Fauna && $tank !== null && $item->has('Wielkość')) {
            $fish = FishBand::fromLabel($item->raw('Wielkość'));
            if ($fish !== null && $fish->index() - $tank->index() >= 3) {
                return false;
            }
        }
        if ($context->volume !== null && $item->has('WielkośćAkwarium') && ! $this->fitsVolume($item, $context->volume)) {
            return false;
        }

        return ! ($context->type !== null && $item->has('TypAkwarium') && ! in_array($context->type, $item->tokens('TypAkwarium'), true));
    }

    /** „Wszystkie” pasuje zawsze; fauna i flora: dowolne pasmo rekordu nie większe od wybranego; reszta: dokładnie. */
    private function fitsVolume(Item $item, string $volume): bool
    {
        $sizes = $item->tokens('WielkośćAkwarium');
        if ($sizes === []) {
            return false;
        }
        if (in_array(Vocabulary::WILDCARD, $sizes, true)) {
            return true;
        }
        $wanted = TankBand::fromLabel($volume)?->index();
        if (! in_array($item->area, [GuideArea::Fauna, GuideArea::Flora], true) || $wanted === null) {
            return in_array($volume, $sizes, true);
        }

        return array_filter($sizes, fn (string $s) => ($i = TankBand::fromLabel($s)?->index()) !== null && $wanted >= $i) !== [];
    }

    /**
     * Lejek fauny w kolejności pytań wybranego typu (bez typu i wielkości, która tylko punktuje): jeśli ktokolwiek ma ocenę
     * co najmniej 2, zostają rekordy z oceną większą od 0; w przeciwnym razie pula bez zmian.
     *
     * @param  list<Item>  $pool
     * @param  array<string, string>  $answers
     * @return list<Item>
     */
    private function funnel(array $pool, array $answers): array
    {
        foreach (Vocabulary::plan(GuideArea::Fauna, $answers['Typ'] ?? null) as $column) {
            $answer = $answers[$column] ?? '';
            if ($pool === [] || in_array($column, ['Typ', 'Wielkość'], true) || in_array($answer, ['', Vocabulary::UNKNOWN], true)) {
                continue;
            }
            $scores = array_map(fn (Item $i) => Scoring::column(GuideArea::Fauna, $column, $answer, $i), $pool);
            if (max($scores) >= 2) {
                $pool = array_values(array_filter($pool, fn (Item $i, int $n) => $scores[$n] > 0, ARRAY_FILTER_USE_BOTH));
            }
        }

        return $pool;
    }

    private function finish(Context $context): Result|Verification
    {
        if ($context->find) {
            return $this->verifier->verify($context->record ?? throw new LogicException('Brak rekordu.'), $context->answers);
        }
        $area = $context->area ?? throw new LogicException('Brak obszaru.');
        $criteria = array_values(array_filter(
            Vocabulary::plan($area, $context->answers['Typ'] ?? null),
            fn (string $c) => $c !== 'Typ' && ! in_array($context->answers[$c] ?? '', ['', Vocabulary::UNKNOWN], true),
        ));

        return (new Ranker($this->rules, $area, $criteria, $context->answers, $context->volume, $context->type))
            ->rank($this->pool($context, $context->answers), $this->label($area, $context->answers));
    }
}

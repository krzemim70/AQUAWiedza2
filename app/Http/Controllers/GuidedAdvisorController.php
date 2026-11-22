<?php

namespace App\Http\Controllers;

use App\Domain\Advisor\Actions\FindProduct;
use App\Domain\Advisor\Actions\RunGuidedAdvisor;
use App\Domain\Advisor\Engine\Answer;
use App\Domain\Advisor\Engine\InvalidAnswers;
use App\Domain\Advisor\Engine\Option;
use App\Domain\Advisor\Engine\Question;
use App\Domain\Advisor\Engine\Result;
use App\Domain\Advisor\Engine\Verification;
use App\Domain\Advisor\GuidedStep;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Http\AdvisorReturn;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Crypt;
use JsonException;

class GuidedAdvisorController
{
    public function __construct(private RunGuidedAdvisor $run, private Catalog $catalog) {}

    public function show(Request $request, FindProduct $find, Settings $settings): View|RedirectResponse
    {
        try {
            [$batch, $answers, $carry] = $this->state($request);
            $guided = $this->run->handle($answers, $batch);
        } catch (DecryptException|JsonException|InvalidAnswers) {
            return $this->restart();
        }

        $step = $guided->step;
        $params = $this->parameters($guided->batch, $carry ?: $guided->answers);
        $query = $request->query('q');
        $phrase = is_string($query) ? trim($query) : '';
        $searchMin = (int) $settings->get(SettingKey::SearchMinChars);
        $phraseTooShort = mb_strlen(Catalog::fold($phrase)) < $searchMin;
        $hits = $step instanceof Question && $step->search && ! $phraseTooShort ? $find->handle($phrase, $guided->batch) : [];
        $entries = $this->entries($guided);
        $profile = [];
        foreach ($guided->questions as $i => $question) {
            $code = $guided->answers[$i]->code;
            $label = $question->search
                ? ($entries[$code]->name ?? $code)
                : (array_find($question->options, fn (Option $option) => $option->code === $code)->label ?? $code);
            $profile[] = ['question' => $question->text, 'answer' => $label];
        }

        $tank = [];
        foreach ($guided->answers as $answer) {
            $key = match ($answer->key) {
                'volume', 'verify.volume' => 'volume',
                'type', 'verify.type' => 'type',
                default => null,
            };
            if ($key !== null) {
                $tank[] = new Answer($key, $answer->code);
            }
        }

        return view('advisor.guided', [
            'step' => $step,
            'profile' => $profile,
            'entries' => $entries,
            'params' => $params,
            'catalogBackTo' => AdvisorReturn::results($request->getRequestUri()),
            'backUrl' => $answers === [] ? null : route('advisor.guided', $this->parameters($guided->batch, array_slice($answers, 0, -1))),
            'nextAreaUrl' => route('advisor.guided', count($tank) === 2 ? $this->parameters($guided->batch, $tank) : []),
            'carryingTank' => $carry !== [],
            'phrase' => $phrase,
            'phraseTooShort' => $phraseTooShort,
            'searchMin' => $searchMin,
            'hits' => $hits,
        ]);
    }

    public function answer(Request $request): RedirectResponse
    {
        $key = $request->input('key');
        $code = $request->input('code');
        $answer = new Answer(is_string($key) ? $key : '', is_string($code) ? $code : '');

        try {
            [$batch, $answers, $carry] = $this->state($request);
            $guided = $this->run->handle($answers, $batch, $answer);
        } catch (DecryptException|JsonException|InvalidAnswers) {
            return $this->restart();
        }

        if ($guided->rejection !== null) {
            $token = $request->query('s');
            $params = is_string($token) ? ['s' => $token] : $this->parameters($guided->batch, $guided->answers);
            $q = $request->query('q');
            if ($guided->step instanceof Question && $guided->step->search && is_string($q)) {
                $params['q'] = $q;
            }

            return redirect()->route('advisor.guided', $params, 303)->with('advisorRejection', $guided->rejection);
        }

        if ($carry !== [] && $answer->code !== 'find') {
            try {
                $guided = $this->run->handle([...$guided->answers, ...$carry], $guided->batch);
            } catch (InvalidAnswers) {
                // Nowy obszar może mieć inny zestaw typów akwarium.
            }
        }

        return redirect()->route('advisor.guided', $this->parameters($guided->batch, $guided->answers), 303);
    }

    /** @return array{?int, list<Answer>, list<Answer>} */
    private function state(Request $request): array
    {
        if (! $request->query->has('s')) {
            return [null, [], []];
        }
        $token = $request->query('s');
        if (! is_string($token)) {
            throw new InvalidAnswers('Nieprawidłowy zapis doboru.');
        }

        $state = json_decode(Crypt::decryptString($token), true, flags: JSON_THROW_ON_ERROR);
        if (! is_array($state) || ! array_key_exists('batch', $state) || ($state['batch'] !== null && (! is_int($state['batch']) || $state['batch'] < 1))
            || ! is_array($state['answers'] ?? null) || ! array_is_list($state['answers'])) {
            throw new InvalidAnswers('Nieprawidłowy zapis doboru.');
        }

        $answers = [];
        foreach ($state['answers'] as $item) {
            if (! is_array($item) || ! is_string($item['key'] ?? null) || ! is_string($item['code'] ?? null)) {
                throw new InvalidAnswers('Nieprawidłowy zapis odpowiedzi.');
            }
            $answers[] = new Answer($item['key'], $item['code']);
        }

        // Sam litraż i typ oznaczają profil do przejęcia po wybraniu celu.
        if (array_map(fn (Answer $answer) => $answer->key, $answers) === ['volume', 'type']) {
            return [$state['batch'], [], $answers];
        }

        return [$state['batch'], $answers, []];
    }

    /**
     * @param  list<Answer>  $answers
     * @return array{s: string}
     */
    private function parameters(?int $batch, array $answers): array
    {
        return ['s' => Crypt::encryptString(json_encode(['batch' => $batch, 'answers' => $answers], JSON_THROW_ON_ERROR))];
    }

    /** @return array<string, CatalogEntry|null> */
    private function entries(GuidedStep $guided): array
    {
        $step = $guided->step;
        $entries = [];
        $cards = match (true) {
            $step instanceof Result => $step->cards,
            $step instanceof Verification => array_filter([$step, $step->alternative, $step->correction]),
            default => [],
        };
        foreach ($cards as $card) {
            $entries[$card->area->value.':'.$card->id] = $this->catalog->find(Area::from($card->area->value), $card->id, $guided->batch);
        }

        $record = array_find($guided->answers, fn (Answer $answer) => $answer->key === 'record');
        if ($record !== null && ! array_key_exists($record->code, $entries)) {
            [$area, $id] = explode(':', $record->code, 2);
            $entries[$record->code] = $this->catalog->find(Area::from($area), $id, $guided->batch);
        }

        return $entries;
    }

    private function restart(): RedirectResponse
    {
        return redirect()->route('advisor.guided', [], 303)->with('advisorStatus', 'Nie udało się odczytać zapisu doboru. Możesz zacząć od nowa.');
    }
}

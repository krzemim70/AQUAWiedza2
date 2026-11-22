<?php

namespace App\Domain\Advisor\Contracts;

use App\Domain\Advisor\Engine\Compatibility\CompatibilityEngine;
use App\Domain\Advisor\Engine\Compatibility\CompatibilityReport;
use App\Domain\Advisor\Engine\Compatibility\Severity;
use App\Domain\Advisor\Engine\Compatibility\StockLine;
use App\Domain\Advisor\Engine\Compatibility\TankContext;
use App\Domain\Advisor\Engine\FaunaRecord;
use App\Domain\Advisor\Engine\FloraRecord;
use App\Domain\Advisor\Engine\GuideArea;
use App\Domain\Advisor\Engine\Temperament;
use App\Domain\Advisor\FaunaPool;
use App\Domain\Advisor\FloraPool;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;

/** Kontrakt zgodności obsady: rozwiązuje pozycje na rekordy bazy wiedzy i wywołuje czysty silnik z ustawieniami. */
class Compatibility
{
    public function __construct(private FaunaPool $fauna, private FloraPool $flora, private Settings $settings) {}

    /**
     * Bez kandydata ocenia całą obsadę; z kandydatem tylko to, co go dotyczy (kandydat spoza katalogu daje pusty wynik).
     *
     * @param  list<StockLine>  $stock
     */
    public function check(TankContext $tank, array $stock, ?StockLine $candidate = null): CompatibilityReport
    {
        $index = $this->index();
        [$records, $unknown] = $this->resolve($stock, $index);
        $subject = $candidate === null ? null : ($index[$candidate->area->value.':'.$candidate->id] ?? null);
        if ($candidate !== null && $subject === null) {
            return new CompatibilityReport($tank->size !== null, [], [], $unknown);
        }

        return $this->engine()->check($tank, $records, $subject)->withUnknown($unknown);
    }

    /**
     * Liczba uwag i konfliktów (bez informacji) dla każdego kandydata z fauny, z jednym zbudowaniem katalogu.
     *
     * @param  list<StockLine>  $stock
     * @param  list<string>  $faunaIds
     * @return array<string, int>
     */
    public function notesFor(TankContext $tank, array $stock, array $faunaIds): array
    {
        $index = $this->index();
        [$records] = $this->resolve($stock, $index);
        $engine = $this->engine();
        $counts = [];
        foreach ($faunaIds as $id) {
            if (isset($index["fauna:$id"])) {
                $report = $engine->check($tank, $records, $index["fauna:$id"]);
                $counts[$id] = $report->count(Severity::Conflict) + $report->count(Severity::Warning);
            }
        }

        return $counts;
    }

    /** @return array<string, FaunaRecord|FloraRecord> */
    private function index(): array
    {
        $index = [];
        foreach ($this->fauna->records() as $r) {
            $index['fauna:'.$r->id] = $r;
        }
        foreach ($this->flora->records() as $r) {
            $index['flora:'.$r->id] = $r;
        }

        return $index;
    }

    /**
     * @param  list<StockLine>  $stock
     * @param  array<string, FaunaRecord|FloraRecord>  $index
     * @return array{list<FaunaRecord|FloraRecord>, int}
     */
    private function resolve(array $stock, array $index): array
    {
        $records = [];
        $unknown = 0;
        foreach ($stock as $line) {
            $record = in_array($line->area, [GuideArea::Fauna, GuideArea::Flora], true) ? ($index[$line->area->value.':'.$line->id] ?? null) : null;
            if ($record === null) {
                $unknown++;
            } else {
                $records[] = $record;
            }
        }

        return [$records, $unknown];
    }

    private function engine(): CompatibilityEngine
    {
        $severity = array_map(Severity::from(...), $this->settings->get(SettingKey::CompatSeverity));
        $pairs = array_map(fn (array $p) => ['aggressor' => Temperament::from($p['aggressor']), 'other' => Temperament::from($p['other'])], $this->settings->get(SettingKey::CompatTemperament));

        return new CompatibilityEngine($severity, $pairs);
    }
}

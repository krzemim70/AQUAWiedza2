<?php

namespace App\Domain\Advisor\Engine\WaterHints;

use App\Domain\Advisor\Engine\FaunaRecord;

/**
 * Ostrożna kontrola przeciwwskazań preparatu względem obsady zbiornika.
 * Pasujące przeciwwskazanie albo niepełne dane obsady (rekord spoza katalogu lub bez typu) ukrywają preparat z wyjaśnieniem.
 */
final class ContraindicationGuard
{
    /** @var array<string, string> */
    private const LABELS = [
        'shrimp' => 'krewetki',
        'snails' => 'ślimaki',
        'scaleless_fish' => 'ryby bez łusek',
        'plants' => 'rośliny',
    ];

    /**
     * Zwraca krótkie wyjaśnienie dlaczego preparat został ukryty, albo null gdy brak przeszkód.
     *
     * @param  list<array{target: string, quote: string}>  $contraindications
     * @param  list<FaunaRecord>  $fauna
     */
    public function blockReason(
        array $contraindications,
        array $fauna,
        int $unknownFauna,
        bool $hasFlora,
        int $unknownFlora = 0,
    ): ?string {
        if ($contraindications === []) {
            return null;
        }

        $hasShrimp = false;
        $hasSnails = false;
        $hasFish = false;
        $missingKind = false;

        foreach ($fauna as $record) {
            $kind = mb_strtolower(trim($record->kind));
            match ($kind) {
                'skorupiak' => $hasShrimp = true,
                'ślimak' => $hasSnails = true,
                'ryba' => $hasFish = true,
                default => $missingKind = true,
            };
        }

        $incompleteStock = $unknownFauna > 0 || $unknownFlora > 0 || $missingKind;
        $targets = [];

        foreach ($contraindications as $item) {
            $target = $item['target'];
            $label = self::LABELS[$target] ?? $target;
            $targets[] = $label;

            if ($target === 'shrimp' && $hasShrimp) {
                return "preparat ma przeciwwskazanie dla: {$label} (w obsadzie są skorupiaki)";
            }
            if ($target === 'snails' && $hasSnails) {
                return "preparat ma przeciwwskazanie dla: {$label} (w obsadzie są ślimaki)";
            }
            if ($target === 'plants' && $hasFlora) {
                return "preparat ma przeciwwskazanie dla: {$label} (w zbiorniku są rośliny)";
            }
            if ($target === 'scaleless_fish' && $hasFish) {
                return "preparat ma przeciwwskazanie dla: {$label} (katalog nie określa obecności łusek u ryb w obsadzie)";
            }
        }

        if ($incompleteStock) {
            $list = implode(', ', array_unique($targets));

            return "preparat ma przeciwwskazanie ({$list}), a dane obsady są niepełne (gatunek spoza katalogu lub bez typu)";
        }

        return null;
    }
}

<?php

namespace App\Domain\Aquarium\Actions;

use App\Domain\Advisor\Engine\Evidence;
use App\Domain\Aquarium\Access;
use App\Domain\Aquarium\Data\ReminderHint;
use App\Domain\Aquarium\Models\ReminderType;
use App\Domain\IdentityAccess\Models\Feature;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Data\CatalogEntry;
use App\Domain\KnowledgeBase\Models\Area;
use App\Models\User;

/**
 * Podpowiada rodzaje przypomnień z zapisanej obsady i sprzętu zbiornika, ale tylko tam, gdzie opis rekordu bazy wiedzy
 * w jednym zdaniu mówi o czynności: regularne podmiany (fauna), regularne nawożenie (flora), czyszczenie (filtr z katalogu sprzętu).
 * Nie podaje terminów ani interwałów (baza ich nie zawiera). Bez takiego zdania nie ma podpowiedzi.
 */
class SuggestReminders
{
    public function __construct(private Access $access, private Catalog $catalog) {}

    /** @return list<ReminderHint> */
    public function handle(User $actor, int $tankId): array
    {
        $tank = $this->access->tank($actor, $tankId, Feature::ReminderSuggestions);
        $batch = $this->catalog->activeBatchId();
        $found = [];

        $rows = [...$tank->livestock()->orderBy('id')->get()->all(), ...$tank->equipment()->orderBy('id')->get()->all()];
        foreach ($rows as $row) {
            $entry = $this->catalog->find($row->area, $row->catalog_id);
            if ($entry === null) {
                continue;
            }
            [$type, $column, $text] = $this->action($entry) + [null, null, null];
            if ($type !== null) {
                $found[$type->value][] = ['name' => $entry->name, 'evidence' => new Evidence($column, $text, $entry->id, $batch)];
            }
        }

        $hints = [];
        foreach (ReminderType::cases() as $type) {
            if (isset($found[$type->value])) {
                $hints[] = new ReminderHint($type, $found[$type->value]);
            }
        }

        return $hints;
    }

    /** @return array{ReminderType, string, string}|array{} rodzaj, kolumna i cytowane zdanie */
    private function action(CatalogEntry $entry): array
    {
        [$type, $column, $source, $stem, $regular] = match ($entry->area) {
            Area::Fauna => [ReminderType::WaterChange, 'opis', $entry->description, 'podmian', true],
            Area::Flora => [ReminderType::Fertilization, 'opis', $entry->description, 'nawoż', true],
            Area::Equipment => [ReminderType::FilterCleaning, 'opis_krotki', ($entry->fields['kategoria_glowna'] ?? '') === 'Filtracja' ? ($entry->fields['opis_krotki'] ?? '') : '', 'czyszcz', false],
            default => [null, '', '', '', false],
        };
        foreach (preg_split('/(?<=[.!?])\s+|\R+/u', (string) $source, -1, PREG_SPLIT_NO_EMPTY) ?: [] as $sentence) {
            $lower = mb_strtolower($sentence);
            if (str_contains($lower, $stem) && (! $regular || str_contains($lower, 'regularn'))) {
                return [$type, $column, trim($sentence)];
            }
        }

        return [];
    }
}

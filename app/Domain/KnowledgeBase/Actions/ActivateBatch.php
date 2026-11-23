<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Batch;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Przełącza aktywną partię w jednej transakcji; poprzednia zostaje do przywrócenia. */
class ActivateBatch
{
    public function __construct(private Settings $settings, private Catalog $catalog) {}

    public function handle(Batch $batch, ?User $by = null): void
    {
        if ($by !== null) {
            Gate::forUser($by)->authorize('manage-knowledge');
        }

        DB::transaction(function () use ($batch, $by) {
            $batch = Batch::lockForUpdate()->findOrFail($batch->id);
            if ($batch->state === Batch::FAILED) {
                throw new DomainException('Nieudanej partii nie można aktywować.');
            }
            if ($batch->state === Batch::ACTIVE) {
                return;
            }

            $previous = Batch::query()->active()->lockForUpdate()->first();
            $previous?->update(['state' => Batch::SUPERSEDED, 'active_slot' => null]);
            $batch->update(['state' => Batch::ACTIVE, 'active_slot' => 1, 'activated_at' => now()]);

            activity()->performedOn($batch)->causedBy($by)
                ->withProperties(['previous' => $previous?->id])->log('catalog batch activated');
            $this->checkWaterHintEvidence($batch, $by);
        });
    }

    private function checkWaterHintEvidence(Batch $batch, ?User $by): void
    {
        foreach ($this->settings->get(SettingKey::WaterHintsMap) as $mapping) {
            $id = $mapping['id'];
            $phrase = trim($mapping['phrase'] ?? '');
            $entry = $id === null ? null : $this->catalog->find(Area::from($mapping['area']), $id, $batch->id);
            if ($phrase !== '' && $entry !== null && (
                str_contains($entry->description ?? '', $phrase)
                || str_contains($entry->fields['opis'] ?? $entry->fields['opis_krotki'] ?? '', $phrase)
            )) {
                continue;
            }

            activity()->performedOn($batch)->causedBy($by)
                ->withProperties(['problem' => $mapping['problem'], 'record' => $id])
                ->log('Pominięto wpis mapy pomiarów: brak cytatu w opisie rekordu.');
        }
    }
}

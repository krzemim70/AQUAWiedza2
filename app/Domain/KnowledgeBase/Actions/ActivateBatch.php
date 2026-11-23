<?php

namespace App\Domain\KnowledgeBase\Actions;

use App\Domain\Advisor\CreatorBundle;
use App\Domain\KnowledgeBase\Contracts\Catalog;
use App\Domain\KnowledgeBase\Contracts\WaterHintProducts;
use App\Domain\KnowledgeBase\Models\Area;
use App\Domain\KnowledgeBase\Models\Batch;
use App\Domain\KnowledgeBase\Models\Entry;
use App\Domain\KnowledgeBase\Models\RecordSpec;
use App\Domain\KnowledgeBase\Models\RecordSpecStatus;
use App\Domain\KnowledgeBase\Models\SpecSourceKind;
use App\Domain\KnowledgeBase\RecordSpecValidator;
use App\Domain\Settings\SettingKey;
use App\Domain\Settings\Settings;
use App\Models\User;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Przełącza aktywną partię w jednej transakcji; poprzednia zostaje do przywrócenia. */
class ActivateBatch
{
    public function __construct(
        private Settings $settings,
        private Catalog $catalog,
        private WaterHintProducts $products,
        private RecordSpecValidator $specValidator,
        private ?CreatorBundle $creatorBundle = null,
    ) {}

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
            $this->checkRecordSpecQuotes($batch, $by);
            DB::afterCommit(fn () => ($this->creatorBundle ?? app(CreatorBundle::class))->rebuild());
        });
    }

    private function checkRecordSpecQuotes(Batch $batch, ?User $by): void
    {
        $specs = RecordSpec::query()
            ->where('source_kind', SpecSourceKind::CatalogQuote->value)
            ->whereIn('status', [RecordSpecStatus::Draft->value, RecordSpecStatus::Verified->value])
            ->with('record')
            ->lockForUpdate()
            ->get();

        if ($specs->isEmpty()) {
            return;
        }

        $entries = Entry::query()
            ->where('batch_id', $batch->id)
            ->whereIn('record_id', $specs->pluck('record_id'))
            ->with('description')
            ->get()
            ->keyBy('record_id');

        foreach ($specs as $spec) {
            $reason = $this->specValidator->quoteFailureReason($spec, $entries->get($spec->record_id));
            if ($reason === null) {
                continue;
            }

            $spec->fill([
                'status' => RecordSpecStatus::Disabled,
                'disabled_reason' => $reason,
                'disabled_at' => now(),
            ]);
            $spec->save();

            activity('catalog_record_specs')
                ->performedOn($spec)
                ->causedBy($by)
                ->event('disabled')
                ->withProperties([
                    'batch_id' => $batch->id,
                    'record' => $spec->record->legacy_id,
                    'area' => $spec->record->area->value,
                    'reason' => $reason,
                ])
                ->log('Wyłączono specyfikację rekordu po aktywacji partii: '.$reason);
        }
    }

    private function checkWaterHintEvidence(Batch $batch, ?User $by): void
    {
        foreach ($this->products->loweringPh($batch->id) as $id => $product) {
            if ($product === null) {
                activity()->performedOn($batch)->causedBy($by)
                    ->withProperties(['direction' => 'ph_down', 'record' => $id])
                    ->log('Pominięto preparat po pomiarze: brak zgodnego dowodu w opisie rekordu lub niewłaściwa kategoria.');
            }
        }

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

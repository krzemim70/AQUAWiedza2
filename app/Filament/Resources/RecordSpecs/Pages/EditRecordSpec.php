<?php

namespace App\Filament\Resources\RecordSpecs\Pages;

use App\Domain\KnowledgeBase\Models\RecordSpec;
use App\Domain\KnowledgeBase\Models\SpecSourceKind;
use App\Filament\Resources\RecordSpecs\RecordSpecResource;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRecordSpec extends EditRecord
{
    protected static string $resource = RecordSpecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            RecordSpecResource::verifyAction(function (): void {
                $this->getRecord()->refresh();
                $this->refreshFormData([
                    'status',
                    'verified_by',
                    'verified_at',
                    'disabled_reason',
                    'disabled_at',
                ]);
            }),
        ];
    }

    protected function getSavedNotificationTitle(): ?string
    {
        return 'Zapisano szkic specyfikacji (wymaga ponownej weryfikacji)';
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    protected function mutateFormDataBeforeFill(array $data): array
    {
        if (($data['source_kind'] ?? null) instanceof SpecSourceKind) {
            $data['source_kind'] = $data['source_kind']->value;
        }
        if (! is_array($data['attributes'] ?? null)) {
            $data['attributes'] = [];
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        /** @var RecordSpec $record */
        $saved = RecordSpecResource::saveFromForm($data, $record);
        $record->refresh();

        return $saved;
    }
}

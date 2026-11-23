<?php

namespace App\Filament\Resources\RecordSpecs\Pages;

use App\Filament\Resources\RecordSpecs\RecordSpecResource;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateRecordSpec extends CreateRecord
{
    protected static string $resource = RecordSpecResource::class;

    protected static bool $canCreateAnother = false;

    protected function getCreatedNotificationTitle(): ?string
    {
        return 'Zapisano szkic specyfikacji';
    }

    /**
     * @param  array<string, mixed>  $data
     */
    protected function handleRecordCreation(array $data): Model
    {
        return RecordSpecResource::saveFromForm($data);
    }
}

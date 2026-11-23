<?php

namespace App\Filament\Resources\RecordSpecs\Pages;

use App\Filament\Resources\RecordSpecs\RecordSpecResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRecordSpecs extends ListRecords
{
    protected static string $resource = RecordSpecResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()->label('Dodaj specyfikację'),
        ];
    }
}

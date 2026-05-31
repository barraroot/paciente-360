<?php

namespace App\Filament\Resources\VoiceCatalogResource\Pages;

use App\Filament\Resources\VoiceCatalogResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListVoiceCatalog extends ListRecords
{
    protected static string $resource = VoiceCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\VoiceCatalogResource\Pages;

use App\Filament\Resources\VoiceCatalogResource;
use Filament\Actions\DeleteAction;
use Filament\Resources\Pages\EditRecord;

class EditVoiceCatalog extends EditRecord
{
    protected static string $resource = VoiceCatalogResource::class;

    protected function getHeaderActions(): array
    {
        return [
            DeleteAction::make(),
        ];
    }
}

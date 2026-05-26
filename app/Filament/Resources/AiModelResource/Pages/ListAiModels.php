<?php

namespace App\Filament\Resources\AiModelResource\Pages;

use App\Filament\Resources\AiModelResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListAiModels extends ListRecords
{
    protected static string $resource = AiModelResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make(),
        ];
    }
}

<?php

namespace App\Filament\Resources\Prescriptions\Pages;

use App\Filament\Resources\Prescriptions\PrescriptionResource;
use Filament\Resources\Pages\ListRecords;

/**
 * T170 — Listagem de receitas no painel super admin.
 *
 * Somente leitura — sem CreateAction no header.
 */
class ListPrescriptions extends ListRecords
{
    protected static string $resource = PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}

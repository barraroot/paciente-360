<?php

namespace App\Filament\Resources\Prescriptions\Pages;

use App\Domain\Prescription\Prescription\Prescription;
use App\Filament\Resources\Prescriptions\PrescriptionResource;
use App\Models\AuditLog;
use Filament\Resources\Pages\ViewRecord;

/**
 * T171 — Visualização de receita individual no painel super admin.
 *
 * Registra audit log quando super admin acessa a página de detalhes.
 * Utiliza Infolist definido em PrescriptionResource::infolist().
 */
class ViewPrescription extends ViewRecord
{
    protected static string $resource = PrescriptionResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }

    /**
     * Audit log ao montar a página de visualização (T171).
     */
    public function mount(int|string $record): void
    {
        parent::mount($record);

        try {
            /** @var Prescription $prescription */
            $prescription = $this->getRecord();

            AuditLog::create([
                'tenant_id' => null,
                'user_id' => auth()->id(),
                'actor_type' => 'user',
                'action' => 'super_admin.prescription.viewed',
                'auditable_type' => Prescription::class,
                'auditable_id' => $prescription->id,
                'payload' => [
                    'prescription_id' => $prescription->id,
                    'tenant_id' => $prescription->tenant_id,
                ],
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        } catch (\Throwable) {
            // Audit failures never block UI
        }
    }
}

<?php

namespace App\Domain\Prescription\Renewal;

/**
 * T147 — Helper para a Fase 5 (agenda) e UI descobrirem a receita
 * original a partir de um appointment_id vinculado à renovação.
 *
 * Convenção C7 do plan §11: este módulo NUNCA toca em tabela `appointments`
 * — apenas consulta `prescription_renewals.appointment_id`.
 */
class AppointmentRenewalLookupService
{
    /**
     * Encontra a renovação vinculada a um appointment.
     *
     * Retorna null se não houver renovação para o appointment.
     */
    public function findRenewalByAppointment(int $appointmentId): ?PrescriptionRenewal
    {
        return PrescriptionRenewal::withoutTenantScope()
            ->where('appointment_id', $appointmentId)
            ->first();
    }
}

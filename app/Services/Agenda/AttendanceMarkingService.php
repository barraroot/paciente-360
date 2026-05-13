<?php

namespace App\Services\Agenda;

use App\Events\Agenda\ConsultaMarcacaoRevertida;
use App\Events\Agenda\ConsultaNaoRealizada;
use App\Events\Agenda\ConsultaRealizada;
use App\Models\Agenda\Appointment;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Metrics\AgendaMetricsContract;

/**
 * T106 — Marcação de comparecimento (clarify nº 14).
 *
 * Janela: 7d após starts_at (após isso cron auto-fecha como concluida_sem_registro).
 * Reversão: 48h via ability `appointment.update`; após 48h via
 * `appointment.revert_attendance_marking` (default Admin Clínica).
 */
final class AttendanceMarkingService
{
    public function __construct(
        private readonly AgendaMetricsContract $metrics,
    ) {}

    public function mark(Appointment $appointment, string $status, ?string $motivo, User $actor): Appointment
    {
        if (! in_array($status, ['realizada', 'nao_realizada'], true)) {
            throw new \DomainException('invalid_attendance_status');
        }

        // Janela de 7d
        if ($appointment->starts_at->copy()->addDays(7)->isPast()) {
            throw new \DomainException('appointment_too_old_for_marking');
        }

        $appointment->update([
            'status' => $status,
            'attendance_marked_at' => now(),
            'attendance_marked_by_user_id' => $actor->id,
            'attendance_motivo' => $motivo,
        ]);

        if ($status === 'realizada') {
            ConsultaRealizada::dispatch($appointment->fresh(), $actor->id);
        } else {
            $this->metrics->appointmentNoShowTotal();
            ConsultaNaoRealizada::dispatch(
                $appointment->fresh(),
                $actor->id,
                $appointment->auto_flagged_at,
            );
        }

        return $appointment->fresh();
    }

    public function revert(Appointment $appointment, User $actor): Appointment
    {
        $previousStatus = $appointment->status;

        if (! in_array($previousStatus, ['realizada', 'nao_realizada'], true)) {
            throw new \DomainException('attendance_not_marked');
        }

        // Janela de reversão (48h padrão)
        $tenant = Tenant::find($appointment->tenant_id);
        $windowHours = (int) ($tenant->settings['agenda']['attendance_revert_window_hours'] ?? 48);

        $within = $appointment->attendance_marked_at?->copy()->addHours($windowHours)->isFuture() ?? false;

        if (! $within && ! $actor->can('appointment.revert_attendance_marking')) {
            throw new \DomainException('revert_window_expired');
        }

        $appointment->update([
            'status' => 'scheduled',
            'attendance_marked_at' => null,
            'attendance_marked_by_user_id' => null,
            'attendance_motivo' => null,
        ]);

        ConsultaMarcacaoRevertida::dispatch($appointment->fresh(), $actor->id, $previousStatus);

        return $appointment->fresh();
    }
}

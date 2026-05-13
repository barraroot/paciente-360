<?php

namespace App\Services\Agenda;

use App\Events\Agenda\ConsultaCancelada;
use App\Events\Agenda\ConsultaConfirmada;
use App\Events\Agenda\ReagendamentoSolicitadoPeloPaciente;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\ConfirmationDispatch;
use App\Support\Metrics\AgendaMetricsContract;
use Illuminate\Support\Carbon;

/**
 * T105 — Processa resposta do paciente vinda da Fase 3 (US-6.4 / clarify nº 6).
 *
 * Resposta `1` → confirmed; `2` → IA Matricial decide reagendar (futuro);
 * `3` → canceled (motivo=paciente_via_chat).
 *
 * Idempotente — se já existe response_value gravada, retorna sem reprocessar (FR-023).
 */
final class ConfirmationResponseProcessor
{
    public function __construct(
        private readonly AgendaMetricsContract $metrics,
    ) {}

    public function process(Appointment $appointment, string $value, string $kind, ?Carbon $receivedAt = null): Appointment
    {
        $receivedAt = $receivedAt ?? now();

        $dispatch = ConfirmationDispatch::query()
            ->where('appointment_id', $appointment->id)
            ->where('kind', $kind)
            ->first();

        // Idempotência: se já registrado, retorna o appointment (sem reemitir)
        if ($dispatch?->response_value !== null) {
            return $appointment;
        }

        // Reverse-idempotency (AC-6.4.6): consulta já cancelada manualmente
        if ($appointment->status === 'canceled') {
            $dispatch?->update([
                'response_received_at' => $receivedAt,
                'response_value' => $value,
                'status' => 'canceled',
            ]);

            return $appointment;
        }

        $this->metrics->confirmationResponseTotal($kind, $value);

        switch ($value) {
            case '1':
                $appointment->update(['status' => 'confirmed', 'confirmed_at' => $receivedAt]);
                $dispatch?->update([
                    'response_received_at' => $receivedAt,
                    'response_value' => $value,
                    'status' => 'confirmed',
                ]);
                ConsultaConfirmada::dispatch($appointment->fresh(), $kind);
                break;

            case '3':
                $appointment->update([
                    'status' => 'canceled',
                    'quem_cancelou' => 'paciente',
                    'motivo_cancelamento' => 'paciente_via_chat',
                    'canceled_at' => $receivedAt,
                ]);
                $dispatch?->update([
                    'response_received_at' => $receivedAt,
                    'response_value' => $value,
                    'status' => 'canceled',
                ]);
                ConsultaCancelada::dispatch($appointment->fresh(), 'paciente', 'paciente_via_chat');
                break;

            case '2':
                // Reagendamento via IA — emite evento; quem orquestra é a IA Matricial (futura).
                $dispatch?->update([
                    'response_received_at' => $receivedAt,
                    'response_value' => $value,
                    'status' => 'reschedule_requested',
                ]);
                event(new ReagendamentoSolicitadoPeloPaciente($appointment));
                break;
        }

        return $appointment->fresh();
    }
}

<?php

namespace App\Services\Agenda;

use App\Events\Agenda\CancelamentoSolicitadoForaDoPrazo;
use App\Events\Agenda\ConsultaCancelada;
use App\Events\Agenda\ConsultaCriada;
use App\Events\Agenda\ConsultaReagendada;
use App\Events\Agenda\LimiteDeReagendamentoExcedido;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentReschedule;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\ProfessionalSchedule;
use App\Models\Agenda\SlotReservation;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Metrics\AgendaMetricsContract;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * T072 — Service central de Appointments (US-6.3 / 6.5).
 *
 * Cobre: create() / reschedule(). Cancel/markAttendance ficam em service
 * dedicado em US4/US5.
 */
final class AppointmentService
{
    public function __construct(
        private readonly AgendaMetricsContract $metrics,
    ) {}

    /**
     * Cria consulta. Idempotente via idempotency_key.
     *
     * @param array<string, mixed> $data
     *
     * @throws SlotConflictException quando race condition bate na PARTIAL UNIQUE
     * @throws \DomainException quando profissional sem agenda ou tipo inativo
     */
    public function create(array $data, ?User $actor = null): array
    {
        // Idempotency replay (R8)
        if (! empty($data['idempotency_key'])) {
            $existing = Appointment::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();

            if ($existing) {
                return ['appointment' => $existing, 'idempotent_replay' => true];
            }
        }

        $type = AppointmentType::findOrFail($data['appointment_type_id']);

        if (! $type->is_active) {
            throw new \DomainException('appointment_type_inactive');
        }

        $professional = Professional::findOrFail($data['professional_id']);

        // FR-002b — bloqueia se profissional sem ProfessionalSchedule
        if (! ProfessionalSchedule::query()->where('professional_id', $professional->id)->exists()) {
            throw new \DomainException('professional_schedule_not_configured');
        }

        $startsAt = Carbon::parse($data['starts_at']);
        $endsAt = $startsAt->copy()->addMinutes($type->duration_minutes);

        // FR-002a — override de bloqueio exige ability + motivo
        $overrideBlock = (bool) ($data['override_block'] ?? false);
        if ($overrideBlock && empty($data['override_motivo'])) {
            throw new \DomainException('override_motivo_required');
        }

        $valorAplicado = isset($data['valor_override'])
            ? (float) $data['valor_override']
            : (float) $type->valor_particular;

        try {
            $appointment = DB::transaction(function () use (
                $data, $type, $professional, $startsAt, $endsAt, $valorAplicado, $overrideBlock, $actor,
            ) {
                $appointment = Appointment::create([
                    'tenant_id' => $professional->tenant_id,
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                    'paciente_id' => $data['paciente_id'],
                    'professional_id' => $professional->id,
                    'appointment_type_id' => $type->id,
                    'starts_at' => $startsAt,
                    'ends_at' => $endsAt,
                    'status' => 'scheduled',
                    'channel_origin' => $data['channel_origin'],
                    'created_by_user_id' => $actor?->id,
                    'valor_aplicado' => $valorAplicado,
                    'valor_override_motivo' => $data['valor_override_motivo'] ?? null,
                    'override_block' => $overrideBlock,
                    'override_motivo' => $data['override_motivo'] ?? null,
                    'notes' => $data['notes'] ?? null,
                ]);

                // Commit reservation se vier reservation_id no payload
                if (! empty($data['reservation_id'])) {
                    $reservation = SlotReservation::find($data['reservation_id']);
                    if ($reservation && $reservation->released_at === null) {
                        $reservation->update([
                            'released_at' => now(),
                            'release_reason' => 'committed',
                        ]);
                    }
                }

                return $appointment;
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                // PARTIAL UNIQUE em (tenant_id, professional_id, starts_at) WHERE status IN active
                throw new SlotConflictException(holderType: 'appointment', expiresAt: null);
            }
            throw $e;
        }

        $this->metrics->appointmentCreatedTotal($type->slug, $appointment->channel_origin);

        ConsultaCriada::dispatch($appointment, $data['notify_patient'] ?? true);

        return ['appointment' => $appointment, 'idempotent_replay' => false];
    }

    /**
     * Reagenda consulta (clarify nº 7 — preserva prof+tipo, limita 2 reagendamentos).
     *
     * @param array<string, mixed> $data
     *
     * @throws \DomainException reschedule_limit_exceeded | slot_conflict
     */
    public function reschedule(Appointment $appointment, array $data, ?User $actor = null): Appointment
    {
        // Idempotency replay
        if (! empty($data['idempotency_key'])) {
            $existing = AppointmentReschedule::query()
                ->where('idempotency_key', $data['idempotency_key'])
                ->first();
            if ($existing) {
                return $appointment->fresh();
            }
        }

        $tenant = Tenant::find($appointment->tenant_id);
        $limit = (int) ($tenant->settings['agenda']['max_reschedules_per_appointment'] ?? 2);

        $rescheduleCount = $appointment->reschedules()->count();

        if ($rescheduleCount >= $limit) {
            // Emite evento + lança DomainException com info estruturada
            LimiteDeReagendamentoExcedido::dispatch($appointment, $rescheduleCount, $limit);

            throw new \DomainException(json_encode([
                'error' => 'reschedule_limit_exceeded',
                'escalated_to_inbox' => true,
                'current_count' => $rescheduleCount,
                'limit' => $limit,
            ]));
        }

        $newStartsAt = Carbon::parse($data['new_starts_at']);
        $duration = $appointment->starts_at->diffInMinutes($appointment->ends_at);
        $newEndsAt = $newStartsAt->copy()->addMinutes((int) $duration);

        $startsAtAnterior = $appointment->starts_at->copy();

        try {
            DB::transaction(function () use ($appointment, $newStartsAt, $newEndsAt, $startsAtAnterior, $data, $actor): void {
                $appointment->update([
                    'starts_at' => $newStartsAt,
                    'ends_at' => $newEndsAt,
                ]);

                AppointmentReschedule::create([
                    'tenant_id' => $appointment->tenant_id,
                    'appointment_id' => $appointment->id,
                    'idempotency_key' => $data['idempotency_key'] ?? null,
                    'starts_at_anterior' => $startsAtAnterior,
                    'starts_at_novo' => $newStartsAt,
                    'quem_solicitou' => $data['quem_solicitou'] ?? ($actor ? 'atendente' : 'ia'),
                    'motivo' => $data['motivo'] ?? null,
                ]);
            });
        } catch (QueryException $e) {
            if ($e->getCode() === '23505') {
                throw new SlotConflictException(holderType: 'appointment', expiresAt: null);
            }
            throw $e;
        }

        ConsultaReagendada::dispatch(
            $appointment->fresh(),
            $startsAtAnterior,
            $newStartsAt,
            $data['quem_solicitou'] ?? ($actor ? 'atendente' : 'ia'),
            $data['motivo'] ?? null,
        );

        return $appointment->fresh();
    }

    /**
     * T121 — Cancela consulta com política de prazo (clarify nº 3).
     *
     * Regras:
     *  - quem_cancelou=profissional → irrestrito (motivo obrigatório, audit)
     *  - quem_cancelou=atendente   → irrestrito (operação humana via painel)
     *  - quem_cancelou=paciente OR via_ia → valida tenant.min_cancellation_hours
     *    com override por type.min_cancellation_hours.
     *    Fora do prazo → DomainException + emit CancelamentoSolicitadoForaDoPrazo.
     *
     * @param array<string, mixed> $data
     *
     * @throws \DomainException cancellation_outside_window | invalid_status
     */
    public function cancel(Appointment $appointment, array $data, ?User $actor = null): Appointment
    {
        if (in_array($appointment->status, Appointment::TERMINAL_STATUSES, true)) {
            throw new \DomainException('appointment_already_terminal');
        }

        $quem = $data['quem_cancelou'];
        $isPatientOrIa = in_array($quem, ['paciente', 'ia'], true);

        if ($isPatientOrIa) {
            $tenant = Tenant::find($appointment->tenant_id);
            $tenantWindow = (int) ($tenant->settings['agenda']['min_cancellation_hours'] ?? 4);
            $type = $appointment->appointmentType;
            $window = $type->min_cancellation_hours !== null && $type->min_cancellation_hours !== ''
                ? (int) $type->min_cancellation_hours
                : $tenantWindow;

            $hoursUntil = now()->floatDiffInHours($appointment->starts_at, false);

            if ($hoursUntil < $window) {
                CancelamentoSolicitadoForaDoPrazo::dispatch(
                    $appointment,
                    $quem,
                    $window,
                    $hoursUntil,
                );

                throw new \DomainException(json_encode([
                    'error' => 'cancellation_outside_window',
                    'escalated_to_inbox' => true,
                    'window_hours' => $window,
                    'current_hours_until_appt' => round($hoursUntil, 2),
                ]));
            }
        }

        $appointment->update([
            'status' => 'canceled',
            'quem_cancelou' => $quem,
            'motivo_cancelamento' => $data['motivo'],
            'canceled_at' => now(),
        ]);

        $this->metrics->appointmentCanceledTotal($quem);

        ConsultaCancelada::dispatch($appointment->fresh(), $quem, $data['motivo']);

        return $appointment->fresh();
    }
}

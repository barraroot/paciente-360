<?php

namespace App\Services\Agenda;

use App\Events\Agenda\VagaAbertaNaListaDeEspera;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\WaitlistEntry;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Metrics\AgendaMetricsContract;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * T130 — Lista de espera FIFO sequencial K=1 (clarify nº 8).
 *
 * Operações:
 *  - enroll(): inscreve paciente; calcula position FIFO.
 *  - notifyNext(): atomicamente seleciona próximo da fila e marca status=notified +
 *    expires_at = now() + tenant.waitlist_confirmation_minutes (default 15).
 *  - accept(): paciente aceita vaga → cria Appointment via AppointmentService.
 *  - expireNotifications(): cron 1min — marca expired e chama notifyNext do próximo.
 */
final class WaitlistService
{
    public function __construct(
        private readonly AppointmentService $appointmentService,
        private readonly AgendaMetricsContract $metrics,
    ) {}

    public function enroll(Paciente $paciente, Professional $professional, AppointmentType $type): WaitlistEntry
    {
        return DB::transaction(function () use ($paciente, $professional, $type) {
            $maxPosition = WaitlistEntry::query()
                ->where('professional_id', $professional->id)
                ->where('appointment_type_id', $type->id)
                ->max('position') ?? 0;

            return WaitlistEntry::create([
                'tenant_id' => $paciente->tenant_id,
                'paciente_id' => $paciente->id,
                'professional_id' => $professional->id,
                'appointment_type_id' => $type->id,
                'status' => 'waiting',
                'position' => $maxPosition + 1,
            ]);
        });
    }

    /**
     * Notifica o próximo da fila (atomicamente).
     */
    public function notifyNext(int $tenantId, int $professionalId, int $appointmentTypeId, ?Carbon $slotStartsAt = null): ?WaitlistEntry
    {
        $tenant = Tenant::find($tenantId);
        $windowMinutes = (int) ($tenant->settings['agenda']['waitlist_confirmation_minutes'] ?? 15);

        // SELECT FOR UPDATE para garantir 1 candidato por evento (defesa em profundidade FR-033)
        return DB::transaction(function () use ($tenantId, $professionalId, $appointmentTypeId, $slotStartsAt, $windowMinutes) {
            $next = WaitlistEntry::query()
                ->where('tenant_id', $tenantId)
                ->where('professional_id', $professionalId)
                ->where('appointment_type_id', $appointmentTypeId)
                ->where('status', 'waiting')
                ->orderBy('position')
                ->lockForUpdate()
                ->first();

            if (! $next) {
                return null;
            }

            $next->update([
                'status' => 'notified',
                'notified_at' => now(),
                'notified_for_slot_starts_at' => $slotStartsAt,
                'expires_at' => now()->addMinutes($windowMinutes),
            ]);

            VagaAbertaNaListaDeEspera::dispatch($next->fresh(), $windowMinutes);

            return $next->fresh();
        });
    }

    /**
     * Paciente aceita vaga: cria Appointment para o slot oferecido.
     *
     * @param array<string, mixed> $extras ex.: ['idempotency_key' => uuid]
     *
     * @throws \DomainException waitlist_offer_expired | slot_no_longer_available
     */
    public function accept(WaitlistEntry $entry, array $extras = [], ?User $actor = null): Appointment
    {
        if ($entry->status !== 'notified') {
            throw new \DomainException('waitlist_entry_not_notified');
        }

        if ($entry->expires_at?->isPast()) {
            throw new \DomainException('waitlist_offer_expired');
        }

        $result = $this->appointmentService->create([
            'idempotency_key' => $extras['idempotency_key'] ?? null,
            'paciente_id' => $entry->paciente_id,
            'professional_id' => $entry->professional_id,
            'appointment_type_id' => $entry->appointment_type_id,
            'starts_at' => $entry->notified_for_slot_starts_at,
            'channel_origin' => 'autoatendimento',
        ], $actor);

        $entry->update([
            'status' => 'accepted',
            'accepted_appointment_id' => $result['appointment']->id,
        ]);

        $this->metrics->waitlistNotificationTotal('accepted');

        return $result['appointment'];
    }

    public function cancel(WaitlistEntry $entry): WaitlistEntry
    {
        $entry->update(['status' => 'canceled']);
        $this->metrics->waitlistNotificationTotal('canceled');

        return $entry->fresh();
    }

    /**
     * Cron — expira notificações vencidas e re-notifica o próximo.
     *
     * @return int número de entries expiradas
     */
    public function expireNotifications(): int
    {
        $expired = WaitlistEntry::query()
            ->where('status', 'notified')
            ->where('expires_at', '<=', now())
            ->get();

        foreach ($expired as $entry) {
            $entry->update(['status' => 'expired']);
            $this->metrics->waitlistNotificationTotal('expired');

            // Notifica o próximo da mesma fila (mesmo slot oferecido)
            $this->notifyNext(
                $entry->tenant_id,
                $entry->professional_id,
                $entry->appointment_type_id,
                $entry->notified_for_slot_starts_at,
            );
        }

        return $expired->count();
    }
}

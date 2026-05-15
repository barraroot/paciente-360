<?php

namespace App\Services\Agenda;

use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\SlotReservation;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;

/**
 * T071 — Reserva pessimista soft de slot (clarify nº 2 / R4).
 *
 * TTL diferenciado por holder:
 *  - user (painel humano) — `tenant.settings.agenda.slot_reservation_ttl_user_minutes` (5 min)
 *  - ia (IA Matricial)   — `tenant.settings.agenda.slot_reservation_ttl_ia_minutes` (2 min)
 *
 * Em conflito de INSERT (PG 23505 unique_violation), retorna SlotConflictException
 * estruturada com info do holder atual.
 */
final class SlotReservationService
{
    public const HOLDER_USER = 'user';

    public const HOLDER_IA = 'ia';

    /**
     * Tenta reservar o slot. Lança SlotConflictException em conflito.
     *
     * @param array{appointment_type_id:int, holder_type:string, holder_id:string, idempotency_key?:?string} $data
     */
    public function reserve(
        Professional $professional,
        Carbon $startsAt,
        array $data,
    ): SlotReservation {
        $type = AppointmentType::findOrFail($data['appointment_type_id']);
        $ttlMinutes = $this->ttlForHolder($professional->tenant_id, $data['holder_type']);

        try {
            return SlotReservation::create([
                'tenant_id' => $professional->tenant_id,
                'professional_id' => $professional->id,
                'appointment_type_id' => $type->id,
                'starts_at' => $startsAt,
                'holder_type' => $data['holder_type'],
                'holder_id' => $data['holder_id'],
                'idempotency_key' => $data['idempotency_key'] ?? null,
                'acquired_at' => now(),
                'expires_at' => now()->addMinutes($ttlMinutes),
            ]);
        } catch (QueryException $e) {
            // 23505 = unique_violation
            if ($e->getCode() === '23505') {
                $current = SlotReservation::query()
                    ->where('tenant_id', $professional->tenant_id)
                    ->where('professional_id', $professional->id)
                    ->where('starts_at', $startsAt)
                    ->whereNull('released_at')
                    ->first();

                throw new SlotConflictException(
                    holderType: $current?->holder_type ?? 'unknown',
                    expiresAt: $current?->expires_at,
                );
            }
            throw $e;
        }
    }

    public function release(SlotReservation $reservation, string $reason): SlotReservation
    {
        if ($reservation->released_at !== null) {
            return $reservation; // idempotente
        }

        $reservation->update([
            'released_at' => now(),
            'release_reason' => $reason,
        ]);

        return $reservation;
    }

    /**
     * Cleanup periódico — chamado pelo cron `agenda:cleanup-expired-reservations`.
     *
     * @return int número de reservas expiradas
     */
    public function cleanupExpired(): int
    {
        return SlotReservation::query()
            ->whereNull('released_at')
            ->where('expires_at', '<=', now())
            ->update([
                'released_at' => now(),
                'release_reason' => 'expired',
            ]);
    }

    private function ttlForHolder(int $tenantId, string $holderType): int
    {
        $tenant = Tenant::find($tenantId);
        $settings = (array) ($tenant->settings['agenda'] ?? []);

        return $holderType === self::HOLDER_IA
            ? (int) ($settings['slot_reservation_ttl_ia_minutes'] ?? 2)
            : (int) ($settings['slot_reservation_ttl_user_minutes'] ?? 5);
    }
}

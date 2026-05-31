<?php

declare(strict_types=1);

namespace App\Listeners\Crm\Kanban;

use App\Domain\Crm\Kanban\Services\KanbanAutoTransitionService;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Agenda\SlotReservation;
use App\Models\Paciente;
use App\Models\Tenant;

/**
 * **T101 (Fase 18 — US3, FR-018)** — quando a IA coloca um hold tentativo
 * (`SlotReservation` com `holder_type='ia'`), promove o card do paciente
 * para 'agendado'.
 *
 * Escuta o eloquent event `eloquent.created: SlotReservation::class`
 * (registrado em EventServiceProvider) — não modifica `SlotReservationService`
 * da Fase 5 (zero invasivo).
 *
 * Resolve o paciente via `holder_id` (que é o conversation_id quando
 * `holder_type='ia'` — convenção da Fase 17 `HoldSlotTool`).
 */
final class PromoteToScheduledOnHoldPlaced
{
    public function __construct(
        private readonly KanbanAutoTransitionService $transitions,
    ) {}

    public function handle(SlotReservation $reservation): void
    {
        if ($reservation->holder_type !== 'ia') {
            return;
        }

        // `holder_id` é o conversation_id quando holder_type='ia' (Fase 17 HoldSlotTool).
        $conversationId = (int) $reservation->holder_id;
        if ($conversationId <= 0) {
            return;
        }

        $tenant = Tenant::find($reservation->tenant_id);
        if ($tenant === null) {
            return;
        }
        app()->instance('tenant', $tenant);

        $conversation = Conversation::query()
            ->where('tenant_id', $reservation->tenant_id)
            ->find($conversationId);

        if ($conversation === null || $conversation->patient_id === null) {
            return;
        }

        $paciente = Paciente::query()
            ->where('tenant_id', $reservation->tenant_id)
            ->find($conversation->patient_id);

        if ($paciente === null) {
            return;
        }

        $this->transitions->apply($paciente, 'slot_held', [
            'reason' => "Hold tentativo colocado pela IA (reserva #{$reservation->id}).",
        ]);
    }
}

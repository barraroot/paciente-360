<?php

namespace App\Listeners\Agenda;

use App\Domain\Messaging\Notification\DataTransfer\NotificationRequest;
use App\Domain\Messaging\Notification\Enums\NotificationType;
use App\Domain\Messaging\Notification\Services\OutboundNotificationDispatcher;
use App\Events\Agenda\VagaAbertaNaListaDeEspera;
use App\Models\Agenda\WaitlistEntry;

/**
 * Feature 013 — Entrega real da oferta de vaga ao próximo da lista de espera.
 *
 * Substitui o stub de log (Fase 5). A regra de "próximo da fila" e a re-oferta na
 * expiração da janela já são tratadas pela Fase 5 (que re-emite este evento para o
 * próximo); aqui cuidamos apenas da ENTREGA da oferta.
 *
 * Auto-discovered (Laravel 11+) — NÃO registrar manualmente.
 *
 * @see specs/013-outbound-notifications/research.md §R11
 */
class DispatchWaitlistOfferToInbox
{
    public function __construct(
        private readonly OutboundNotificationDispatcher $dispatcher,
    ) {}

    public function handle(VagaAbertaNaListaDeEspera $event): void
    {
        $entry = $event->entry;

        $offerExpiresAt = $entry->notified_for_slot_starts_at !== null
            ? now()->addMinutes($event->notificationWindowMinutes)->toIso8601String()
            : null;

        $this->dispatcher->dispatch(new NotificationRequest(
            tenantId: $entry->tenant_id,
            patientId: $entry->paciente_id,
            type: NotificationType::WaitlistOffer,
            milestone: 'offer',
            sourceType: WaitlistEntry::class,
            sourceId: $entry->id,
            context: array_filter(['offer_expires_at' => $offerExpiresAt], fn ($v) => $v !== null),
            professionalId: $entry->professional_id,
        ));
    }
}

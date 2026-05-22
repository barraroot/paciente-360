<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Listeners;

use App\Domain\Privacy\Events\ConsentimentoRevogado;
use App\Domain\Privacy\Models\ConsentFinalidade;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Log;

/**
 * **T026 (Fase 8 — Lote A US-13.1)** — Notifica Admin Clínica via inbox interna
 * quando paciente revoga marketing (`/sair`).
 *
 * Sinal importante para Princípio VI (Conformidade Meta): clínica precisa
 * saber que esse paciente já não deve ser incluído em próximas campanhas E
 * deve entender o motivo (insatisfação? saturação?). Acompanhamento melhora
 * quality rating do número WhatsApp.
 *
 * **Estado MVP**: usa `Log::info` estruturado em vez de criar `InboxTask`
 * real — mesma decisão DEFERRED da Fase 7 (`createForPatient` não está
 * disponível na Inbox da Fase 3). Quando feature real for entregue,
 * substituir o log por chamada ao serviço da Inbox.
 *
 * Auto-discovered pelo Laravel 13 (sem registro manual no AppServiceProvider —
 * Fase 5 lesson: registro manual duplica execução).
 */
final class NotifyAdminClinicaOnMarketingRevokedListener implements ShouldQueue
{
    public string $queue = 'privacy';

    public function handle(ConsentimentoRevogado $event): void
    {
        // Filtro: apenas marketing dispara notificação. Outras finalidades
        // (transacional / pesquisa) não geram inbox interna.
        if ($event->finalidade !== ConsentFinalidade::Marketing) {
            return;
        }

        // DEFERRED MVP — substitui InboxTask real por log estruturado consumível
        // por dashboard até a feature de inbox da Fase 3 expor `createForPatient`.
        Log::info('privacy.marketing_revoked.notify_admin', [
            'tenant_id' => $event->tenantId,
            'patient_id' => $event->patientId,
            'consent_record_id' => $event->consentRecordId,
            'channel' => $event->channel,
            'revoked_at' => $event->revokedAt->toIso8601String(),
            'scope' => $event->scope,
        ]);
    }
}

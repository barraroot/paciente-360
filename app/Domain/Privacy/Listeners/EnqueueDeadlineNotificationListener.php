<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Listeners;

use App\Domain\Privacy\Events\DireitoEsquecimentoSolicitado;
use App\Domain\Privacy\Events\PortabilidadeDadosSolicitada;
use Illuminate\Support\Facades\Log;

/**
 * **T049 (Fase 8 — Lote A US-13.2)** — Auto-discovered listener.
 *
 * Captura solicitações abertas e LOGA o tracking inicial. As notificações
 * progressivas em D-5/D-2 são entregues pelo cron `privacy:notify-deadlines`
 * (T057), que varre `forgetting_requests`/`portability_requests` diariamente.
 *
 * Função deste listener é apenas confirmar registro + emitir log estruturado
 * inicial que dashboards de privacidade consomem.
 */
final class EnqueueDeadlineNotificationListener
{
    public function handle(DireitoEsquecimentoSolicitado|PortabilidadeDadosSolicitada $event): void
    {
        $type = $event instanceof DireitoEsquecimentoSolicitado ? 'forgetting' : 'portability';

        $requestId = $event instanceof DireitoEsquecimentoSolicitado
            ? $event->forgettingRequestId
            : $event->portabilityRequestId;

        Log::info("privacy.{$type}.requested", [
            'tenant_id' => $event->tenantId,
            'patient_id' => $event->patientId,
            'request_id' => $requestId,
            'deadline_at' => $event->deadlineAt->toIso8601String(),
        ]);
    }
}

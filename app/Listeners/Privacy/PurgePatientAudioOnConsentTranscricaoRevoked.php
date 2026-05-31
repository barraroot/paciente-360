<?php

declare(strict_types=1);

namespace App\Listeners\Privacy;

use App\Domain\Privacy\Events\ConsentimentoRevogado;
use App\Domain\Privacy\Models\ConsentFinalidade;
use App\Jobs\Compliance\PurgePatientExtendedAudioJob;

/**
 * **T212 (Fase 18 — Polish, FR-055c)** — quando paciente revoga
 * `ConsentFinalidade::Transcricao`, enfileira purge retroativo dos áudios
 * brutos dele (>= prazo padrão).
 *
 * Auto-discovered Laravel 11+ — método `handle()` com tipo do evento.
 * Listener é IDEMPOTENTE (re-dispatch do mesmo evento gera nova execução
 * do job, que é por si idempotente — só apaga o que ainda existe).
 *
 * Filtro: apenas `finalidade === Transcricao`. Outras revogações (marketing,
 * etc.) seguem o fluxo normal da Fase 8.
 */
final class PurgePatientAudioOnConsentTranscricaoRevoked
{
    public function handle(ConsentimentoRevogado $event): void
    {
        if ($event->finalidade !== ConsentFinalidade::Transcricao) {
            return;
        }

        PurgePatientExtendedAudioJob::dispatch(
            tenantId: $event->tenantId,
            patientId: $event->patientId,
        );
    }
}

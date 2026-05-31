<?php

declare(strict_types=1);

namespace App\Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T105 (Fase 18 — US3, FR-018)** — emitido quando a IA detecta que iniciou
 * a qualificação do lead (1ª pergunta sobre queixa/contexto).
 *
 * Emitido pelo `AiMessageProcessor` (Fase 17) quando o turno produz uma
 * pergunta de qualificação. Listener `PromoteToQualifyingOnAiQualificationStarted`
 * (T105) move o card de "new" → "qualificando".
 */
final class AiQualificationStarted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly int $pacienteId,
    ) {}
}

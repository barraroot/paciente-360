<?php

declare(strict_types=1);

namespace App\Domain\Ai\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * **T105 (Fase 18 — US3, FR-018)** — emitido quando o paciente aceita o
 * valor da consulta (sinaliza intenção de agendar).
 *
 * Listener `PromoteToNegotiatingOnAiValueAccepted` (T105) move o card
 * para "negociando".
 */
final class AiValueAccepted
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly int $tenantId,
        public readonly int $conversationId,
        public readonly int $pacienteId,
    ) {}
}

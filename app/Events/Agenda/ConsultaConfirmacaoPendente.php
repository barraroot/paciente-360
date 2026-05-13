<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T099 — Evento de domínio: confirmação pendente (US-6.4 / clarify nº 6).
 *
 * Consumido pela Fase 3 que decide:
 *  - via_ia=false → envia template HSM com placeholder de horário (FR-018b)
 *  - via_ia=true  → IA assume e injeta pergunta natural no fluxo conversacional
 */
class ConsultaConfirmacaoPendente
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly string $kind,             // 24h | 2h | retry_30min | 15min_manual_escalation
        public readonly bool $viaIa,
        public readonly string $horarioBrasilia,  // ex.: "14:00"
        public readonly string $tzLabel,          // ex.: "horário de São Paulo"
    ) {}
}

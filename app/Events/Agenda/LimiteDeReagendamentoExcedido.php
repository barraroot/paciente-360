<?php

namespace App\Events\Agenda;

use App\Models\Agenda\Appointment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T119 — Evento de domínio (clarify nº 7 — limite 2 reagendamentos).
 *
 * Consumido pela Fase 3 para criar handoff na inbox para atendente decidir
 * caso a caso (pode ser exceção legítima).
 */
class LimiteDeReagendamentoExcedido
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Appointment $appointment,
        public readonly int $currentCount,
        public readonly int $limit,
    ) {}
}

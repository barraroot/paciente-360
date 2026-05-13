<?php

namespace App\Events\Agenda;

use App\Models\Professional;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * T042 — Evento de domínio: agenda recorrente configurada/atualizada (US-6.1, FR-004).
 */
class ProfissionalAgendaConfigurada
{
    use Dispatchable;
    use SerializesModels;

    public function __construct(
        public readonly Professional $professional,
        public readonly int $changedByUserId,
        public readonly string $effectiveFrom,
    ) {}
}

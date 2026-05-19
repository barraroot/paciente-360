<?php

namespace App\Events\Prescription;

use App\Support\Lgpd\ContainsNoClinicalData;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Support\Carbon;

/**
 * Emitido quando um usuário com permissão acessa o conteúdo completo de
 * uma receita controlada (AC-8.1.6, Q8c).
 *
 * Registra APENAS o evento (actor_user_id, prescription_id, viewed_at, ip,
 * user_agent) — NÃO armazena snapshot dos campos renderizados.
 * Implementa `ContainsNoClinicalData`.
 *
 * @see specs/007-gestao-receituario/spec.md Q8c
 */
final class PrescricaoControladaVisualizada implements ContainsNoClinicalData
{
    use Dispatchable;

    public function __construct(
        public readonly int $actorUserId,
        public readonly int $prescriptionId,
        public readonly int $tenantId,
        public readonly Carbon $viewedAt,
        public readonly ?string $ip,
        public readonly ?string $userAgent,
    ) {}
}

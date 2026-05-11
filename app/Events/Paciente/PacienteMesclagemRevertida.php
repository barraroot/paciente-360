<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `PacienteMesclagemRevertida`
 * (action `paciente.mesclagem_revertida`).
 *
 * Disparado quando uma mescla é desfeita dentro do prazo de 30 dias
 * (`mesclagens_pacientes.reversivel_ate`).
 *
 * @see specs/002-crm-pacientes/spec.md R7
 */
final class PacienteMesclagemRevertida implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    /**
     * @param list<int> $pacientesRestauradosIds IDs dos pacientes que voltaram a ficar ativos.
     * @param int $mesclagemId PK da linha em `mesclagens_pacientes`.
     */
    public function __construct(
        public readonly Paciente $paciente,
        public readonly array $pacientesRestauradosIds,
        public readonly int $mesclagemId,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTE_MESCLAGEM_REVERTIDA;
    }

    public function auditableModel(): ?Model
    {
        return $this->paciente;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'paciente_alvo_id' => $this->paciente->id,
            'pacientes_restaurados_ids' => $this->pacientesRestauradosIds,
            'mesclagem_id' => $this->mesclagemId,
        ];
    }
}

<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `PacienteMesclado` (action `paciente.mesclado`).
 *
 * Disparado pelo `MergeService::merge()` quando dois ou mais pacientes
 * são consolidados. O `auditableModel()` aponta para o paciente ALVO
 * (que permanece ativo); os IDs absorvidos vão no payload.
 *
 * @see specs/002-crm-pacientes/spec.md US-3.1 / R7
 */
final class PacienteMesclado implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    /**
     * @param list<int> $pacientesOrigemIds IDs dos pacientes absorvidos (≥ 1).
     * @param int $mesclagemId PK da linha em `mesclagens_pacientes`.
     */
    public function __construct(
        public readonly Paciente $paciente,
        public readonly array $pacientesOrigemIds,
        public readonly int $mesclagemId,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTE_MESCLADO;
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
            'pacientes_origem_ids' => $this->pacientesOrigemIds,
            'mesclagem_id' => $this->mesclagemId,
        ];
    }
}

<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `PacienteStatusAlterado` (action `paciente.status_alterado`).
 *
 * Disparado SEMPRE que o status muda — inclusive na criação inicial
 * (com `status_anterior = null`, conforme AC-3.1.5). Para transições
 * que exigem motivo (ex.: `bloqueado`), o `motivo` é preenchido.
 *
 * @see specs/002-crm-pacientes/spec.md AC-3.1.5 / AC-3.5
 */
final class PacienteStatusAlterado implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Paciente $paciente,
        public readonly ?string $statusAnterior,
        public readonly string $statusNovo,
        public readonly ?string $motivo = null,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTE_STATUS_ALTERADO;
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
            'paciente_id' => $this->paciente->id,
            'status_anterior' => $this->statusAnterior,
            'status_novo' => $this->statusNovo,
            'motivo' => $this->motivo,
        ];
    }
}

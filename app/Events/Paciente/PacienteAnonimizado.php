<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `PacienteAnonimizado` (action `paciente.anonimizado`).
 *
 * Disparado pelo `AnonimizacaoService` ao zerar PII direta do paciente
 * (FR-035 — LGPD). O paciente permanece na base com `anonimizado_em`
 * preenchido para fins forenses/audit, mas oculto da maioria das queries
 * (global scope `not_anonymized` em `Paciente`).
 *
 * @see specs/002-crm-pacientes/spec.md FR-035
 */
final class PacienteAnonimizado implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Paciente $paciente,
        public readonly ?string $motivo = null,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTE_ANONIMIZADO;
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
            'motivo' => $this->motivo,
        ];
    }
}

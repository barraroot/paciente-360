<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `PacienteAtualizado` (action `paciente.atualizado`).
 *
 * Disparado apenas quando um campo "significativo" muda (AC-3.2.2 / R4):
 * `status`, `telefone_primario`, `email`, `profissional_responsavel_id`,
 * `convenio_principal_id`. Alterações em campos não rastreados (endereço,
 * data de nascimento) NÃO disparam este evento.
 *
 * @see config/paciente.php — `timeline.tracked_fields`
 */
final class PacienteAtualizado implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    /**
     * @param array<string, array{from: mixed, to: mixed}> $changes
     *                                                              Diff dos campos rastreados que de fato mudaram.
     */
    public function __construct(
        public readonly Paciente $paciente,
        public readonly array $changes,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTE_ATUALIZADO;
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
            'changes' => $this->changes,
        ];
    }
}

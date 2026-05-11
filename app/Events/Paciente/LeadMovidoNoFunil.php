<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\FunilColuna;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `LeadMovidoNoFunil` (action `lead.movido_no_funil`).
 *
 * Disparado em qualquer movimentação Kanban — manual (drag-and-drop) ou
 * automática (regras Fase 3+). Quando `colunaAnterior === null`, o
 * paciente entrou no funil pela primeira vez.
 *
 * @see specs/002-crm-pacientes/spec.md US-3.4
 */
final class LeadMovidoNoFunil implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Paciente $paciente,
        public readonly ?FunilColuna $colunaAnterior,
        public readonly FunilColuna $colunaNova,
        public readonly ?string $motivo = null,
        public readonly ?string $motivoOutro = null,
        public readonly bool $automatico = false,
    ) {}

    public function auditAction(): string
    {
        return Actions::LEAD_MOVIDO_NO_FUNIL;
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
            'coluna_anterior_id' => $this->colunaAnterior?->id,
            'coluna_anterior_slug' => $this->colunaAnterior?->slug,
            'coluna_nova_id' => $this->colunaNova->id,
            'coluna_nova_slug' => $this->colunaNova->slug,
            'motivo' => $this->motivo,
            'motivo_outro' => $this->motivoOutro,
            'automatico' => $this->automatico,
        ];
    }
}

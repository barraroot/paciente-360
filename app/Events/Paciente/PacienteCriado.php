<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Paciente;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `PacienteCriado` (action `paciente.criado`).
 *
 * Disparado pelo `PacienteService::create()` após persistência do novo
 * paciente. Audita em `audit_logs` e gera entrada em `eventos_timeline`
 * via listeners da Fase 0 + Fase 2.
 *
 * @see specs/002-crm-pacientes/spec.md AC-3.1.1 / AC-3.1.8
 */
final class PacienteCriado implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Paciente $paciente,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTE_CRIADO;
    }

    public function auditableModel(): ?Model
    {
        return $this->paciente;
    }

    /**
     * Snapshot dos campos relevantes do paciente recém-criado.
     * CPF é mascarado downstream pelo `AuditAttributesBuilder` (T033).
     *
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'paciente_id' => $this->paciente->id,
            'nome' => $this->paciente->nome,
            'cpf' => $this->paciente->cpf,
            'status' => $this->paciente->status,
            'origem' => $this->paciente->origem,
            'origem_detalhe' => $this->paciente->origem_detalhe,
            'origem_origem' => $this->paciente->origem_origem,
            'profissional_responsavel_id' => $this->paciente->profissional_responsavel_id,
            'convenio_principal_id' => $this->paciente->convenio_principal_id,
        ];
    }
}

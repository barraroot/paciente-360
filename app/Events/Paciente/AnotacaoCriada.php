<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Anotacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `AnotacaoCriada` (action `paciente.anotacao_criada`).
 *
 * Anotações são imutáveis (T026 — exception em boot). Este evento marca
 * a criação inicial. Para "edição", o caller emite `AnotacaoRetratada`
 * em paralelo.
 *
 * Visibilidade granular (FR-012) é aplicada na camada de leitura — o
 * audit log e a timeline armazenam o evento normalmente; o filtro
 * acontece no `AnotacaoPolicy::view`.
 *
 * @see specs/002-crm-pacientes/spec.md AC-3.2.4
 */
final class AnotacaoCriada implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Anotacao $anotacao,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTE_ANOTACAO_CRIADA;
    }

    public function auditableModel(): ?Model
    {
        return $this->anotacao;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'anotacao_id' => $this->anotacao->id,
            'paciente_id' => $this->anotacao->paciente_id,
            'tipo' => $this->anotacao->tipo,
            'autor_id' => $this->anotacao->autor_id,
        ];
    }
}

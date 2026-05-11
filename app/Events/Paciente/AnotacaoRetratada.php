<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Anotacao;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `AnotacaoRetratada` (action `paciente.anotacao_retratada`).
 *
 * Imutabilidade preservada: a retratação é uma NOVA `Anotacao` com
 * `retratacao_de_anotacao_id` apontando para a anotação original (que
 * permanece intacta no banco).
 *
 * O `auditableModel()` aponta para a retratação (anotação recém-criada).
 *
 * @see specs/002-crm-pacientes/spec.md AC-3.2.5
 */
final class AnotacaoRetratada implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Anotacao $original,
        public readonly Anotacao $retratacao,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTE_ANOTACAO_RETRATADA;
    }

    public function auditableModel(): ?Model
    {
        return $this->retratacao;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'original_anotacao_id' => $this->original->id,
            'retratacao_anotacao_id' => $this->retratacao->id,
            'paciente_id' => $this->retratacao->paciente_id,
            'tipo' => $this->retratacao->tipo,
            'autor_id' => $this->retratacao->autor_id,
        ];
    }
}

<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Paciente;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `TagRemovida` (action `paciente.tag_removida`).
 *
 * Disparado quando uma tag é desvinculada do paciente.
 *
 * @see specs/002-crm-pacientes/spec.md US-3.5
 */
final class TagRemovida implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Paciente $paciente,
        public readonly Tag $tag,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTE_TAG_REMOVIDA;
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
            'tag_id' => $this->tag->id,
            'tag_nome' => $this->tag->nome,
            'tag_tipo' => $this->tag->tipo,
        ];
    }
}

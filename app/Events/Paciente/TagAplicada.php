<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use App\Models\Paciente;
use App\Models\Tag;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `TagAplicada` (action `paciente.tag_aplicada`).
 *
 * Disparado quando uma tag (livre ou sistêmica) é vinculada ao paciente.
 *
 * @see specs/002-crm-pacientes/spec.md US-3.5
 */
final class TagAplicada implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    public function __construct(
        public readonly Paciente $paciente,
        public readonly Tag $tag,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTE_TAG_APLICADA;
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

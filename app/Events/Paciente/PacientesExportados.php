<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `PacientesExportados` (action `paciente.exported`).
 *
 * Disparado ao concluir a geração de um arquivo de export (CSV/XLSX).
 * Operação em lote — sem `auditableModel`, sem timeline por paciente.
 *
 * @see specs/002-crm-pacientes/spec.md US-3.5 / FR-037
 */
final class PacientesExportados implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    /**
     * @param array<string, mixed> $escopo Filtros aplicados na query (ex.: ['status' => 'ativo']).
     */
    public function __construct(
        public readonly array $escopo,
        public readonly int $contagem,
        public readonly string $arquivoHash,
        public readonly int $tamanhoBytes,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTES_EXPORTADOS;
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    public function relatedPacienteId(): ?int
    {
        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public function auditPayload(): array
    {
        return [
            'escopo' => $this->escopo,
            'contagem' => $this->contagem,
            'arquivo_hash' => $this->arquivoHash,
            'tamanho_bytes' => $this->tamanhoBytes,
        ];
    }
}

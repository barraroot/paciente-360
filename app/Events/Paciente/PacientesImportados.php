<?php

namespace App\Events\Paciente;

use App\Events\Concerns\IsAuditable;
use App\Events\Contracts\Auditable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * T034 — Evento `PacientesImportados` (action `paciente.imported`).
 *
 * Disparado pelo job de importação ao concluir o processamento (mesmo em
 * `partial_failure`). Como representa uma operação em lote, NÃO tem
 * `auditableModel` (e o `RegistraEventoTimelineListener` ignora — não
 * grava em `eventos_timeline` porque não há paciente único).
 *
 * @see specs/002-crm-pacientes/spec.md US-3.3
 */
final class PacientesImportados implements Auditable
{
    use Dispatchable;
    use IsAuditable;

    /**
     * @param array<string, int> $contadores Ex.: ['criados' => 80, 'atualizados' => 15, 'falharam' => 5].
     */
    public function __construct(
        public readonly int $importacaoId,
        public readonly array $contadores,
        public readonly string $arquivoHash,
    ) {}

    public function auditAction(): string
    {
        return Actions::PACIENTES_IMPORTADOS;
    }

    public function auditableModel(): ?Model
    {
        return null;
    }

    /**
     * Importação não tem paciente único — listener de timeline ignora.
     */
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
            'importacao_id' => $this->importacaoId,
            'contadores' => $this->contadores,
            'arquivo_hash' => $this->arquivoHash,
        ];
    }
}

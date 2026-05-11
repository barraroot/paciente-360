<?php

namespace App\Http\Resources\V1;

use App\Models\Importacao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * T186 — Resource de serialização de `Importacao` para a API.
 *
 * Calcula `progress_percent` com base em `checkpoint.linhas_processadas`
 * e `total_linhas`. Não inclui PII — apenas metadados da importação.
 *
 * @mixin Importacao
 */
final class ImportacaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        /** @var Importacao $importacao */
        $importacao = $this->resource;

        $checkpoint = $importacao->checkpoint ?? [];
        $linhasProcessadas = (int) ($checkpoint['linhas_processadas'] ?? 0);
        $totalLinhas = $importacao->total_linhas ?? 0;

        $progressPercent = $totalLinhas > 0
            ? (int) round(($linhasProcessadas / $totalLinhas) * 100)
            : ($importacao->status === 'completed' ? 100 : 0);

        return [
            'id' => $importacao->id,
            'status' => $importacao->status,
            'arquivo_nome_original' => $importacao->arquivo_nome_original,
            'arquivo_tamanho_bytes' => $importacao->arquivo_tamanho_bytes,
            'total_linhas' => $importacao->total_linhas,
            'status_inicial_pacientes' => $importacao->status_inicial_pacientes,
            'progress_percent' => $progressPercent,
            'contadores' => $checkpoint['contadores'] ?? null,
            'relatorio' => $this->when(
                in_array($importacao->status, ['completed', 'partial_failure', 'failed'], true),
                $importacao->relatorio,
            ),
            'started_at' => $importacao->started_at?->toIso8601String(),
            'finished_at' => $importacao->finished_at?->toIso8601String(),
            'created_at' => $importacao->created_at->toIso8601String(),
        ];
    }
}

<?php

namespace App\Services\Pacientes;

use App\Models\Paciente;
use App\Models\Scopes\TenantScope;
use App\Models\Tenant;
use Illuminate\Support\Collection;

/**
 * T111 — Serviço de detecção de duplicatas de pacientes.
 *
 * Estratégia de detecção:
 *  1. Primário: CPF canônico (match exato).
 *  2. Fallback (quando CPF ausente no payload): não realiza dedup por
 *     telefone — telefone não é índice de unicidade (famílias compartilham).
 *
 * Retorna até 5 candidatos do **tenant atual** (nunca cross-tenant).
 * Pacientes anonimizados são excluídos automaticamente pelo global scope.
 *
 * @see specs/002-crm-pacientes/spec.md AC-3.1.3
 */
final class DedupService
{
    private const MAX_CANDIDATOS = 5;

    /**
     * Detecta possíveis duplicatas para o payload de criação de paciente.
     *
     * @param array<string, mixed> $payload
     * @return Collection<int, Paciente>
     */
    public function detectDuplicates(array $payload, Tenant $tenant): Collection
    {
        $cpf = $payload['cpf'] ?? null;

        if (blank($cpf)) {
            return collect();
        }

        return Paciente::withoutGlobalScope(TenantScope::class)
            ->where('tenant_id', $tenant->id)
            ->where('cpf', $cpf)
            ->whereNull('anonimizado_em')
            ->limit(self::MAX_CANDIDATOS)
            ->get();
    }
}

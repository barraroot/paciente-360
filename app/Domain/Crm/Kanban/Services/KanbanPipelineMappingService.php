<?php

declare(strict_types=1);

namespace App\Domain\Crm\Kanban\Services;

use App\Domain\Crm\Kanban\Models\KanbanPipelineMapping;
use App\Models\FunilColuna;

/**
 * **T082 (Fase 18 — US3, FR-019)** — resolução de mapping evento→coluna por
 * tenant.
 *
 * Cada tenant tem seu pipeline; o mapping default vem do
 * `DefaultKanbanPipelineMappingSeeder` (T035, idempotente — Foundational).
 * Tenants novos passam pelo seeder ou pelo
 * {@see LeadOnboardingService::ensurePipelineMappingForTenant}.
 *
 * Caso especial `lead_created` (FR-011): resolve via `funil_colunas.is_initial=true`
 * direto, pois um mapping específico para este evento pode não existir em
 * tenants sem seed (degradado).
 */
final class KanbanPipelineMappingService
{
    public function colunaFor(int $tenantId, string $eventKind): ?FunilColuna
    {
        $mapping = KanbanPipelineMapping::query()
            ->where('tenant_id', $tenantId)
            ->where('event_kind', $eventKind)
            ->where('is_active', true)
            ->first();

        if ($mapping !== null) {
            return FunilColuna::query()
                ->where('tenant_id', $tenantId)
                ->find($mapping->funil_coluna_id);
        }

        // Fallback para `lead_created`: usa a coluna marcada como is_initial
        // mesmo sem mapping cadastrado (tenant pré-seed da Fase 18).
        if ($eventKind === 'lead_created') {
            return $this->initialColumn($tenantId);
        }

        return null;
    }

    public function initialColumn(int $tenantId): ?FunilColuna
    {
        return FunilColuna::query()
            ->where('tenant_id', $tenantId)
            ->where('is_initial', true)
            ->first();
    }
}

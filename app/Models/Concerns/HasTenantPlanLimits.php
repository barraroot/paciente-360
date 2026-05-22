<?php

declare(strict_types=1);

namespace App\Models\Concerns;

/**
 * **T016** — Trait que expõe limites quantitativos do plano vigente do tenant.
 *
 * Consumido pelos lotes B/C/D para enforcement de:
 *   - `daily_campaign_limit`        (Lote C — fragmentação automática de batch)
 *   - `api_rate_limit_per_minute`   (Lote D — middleware ApiPublicRateLimiter)
 *   - `webhook_max_endpoints`       (Lote D — validação de cadastro de webhook)
 *
 * **Estado atual (Fase 8 Phase 1)**: aplicado ao model `Tenant`, lê os 3
 * limites diretamente da coluna `plans` (ALTERada no Lote B — T115).
 *
 * **Estado futuro (Lote B)**: o lookup será via `tenant_plan_bindings →
 * plan_versions.snapshot` (snapshot versioning Q12.2.2). A interface deste
 * trait permanece estável — apenas a fonte da informação muda.
 *
 * **Fallback seguro**: quando o campo não existe ainda (antes do Lote B
 * rodar a migration de ALTER plans) ou plano não tem limite definido,
 * retorna o default conservador de `config('finalization.*_default')`.
 *
 * @property-read \App\Models\Plan|null $plan
 */
trait HasTenantPlanLimits
{
    /**
     * Lookup genérico de limite do plano por chave.
     * Aceita uma das chaves: `daily_campaign_limit`, `api_rate_limit_per_minute`, `webhook_max_endpoints`.
     */
    public function currentPlanLimit(string $key): int
    {
        $value = $this->plan?->{$key} ?? null;

        if (is_int($value) && $value >= 0) {
            return $value;
        }

        // Fallback seguro: defaults conservadores de config/finalization.php.
        return match ($key) {
            'daily_campaign_limit' => (int) config('finalization.daily_campaign_limit_default', 200),
            'api_rate_limit_per_minute' => (int) config('finalization.api_rate_limit_default_per_minute', 100),
            'webhook_max_endpoints' => (int) config('finalization.webhook_max_endpoints_default', 5),
            default => 0,
        };
    }

    /**
     * Atalhos para os 3 limites mais usados.
     */
    public function dailyCampaignLimit(): int
    {
        return $this->currentPlanLimit('daily_campaign_limit');
    }

    public function apiRateLimitPerMinute(): int
    {
        return $this->currentPlanLimit('api_rate_limit_per_minute');
    }

    public function webhookMaxEndpoints(): int
    {
        return $this->currentPlanLimit('webhook_max_endpoints');
    }
}

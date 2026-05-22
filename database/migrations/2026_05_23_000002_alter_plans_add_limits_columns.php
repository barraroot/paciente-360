<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * **T115 (Fase 8 — Lote B US-12.2)** — Adiciona 3 colunas de limites em `plans`.
 *
 * Colunas consumidas por:
 *   - `daily_campaign_limit` → Lote C dispatcher (CampaignComplianceGate)
 *   - `api_rate_limit_per_minute` → Lote D middleware (ApiPublicRateLimiter)
 *   - `webhook_max_endpoints` → Lote D validação cadastro de webhook
 *
 * Defaults conservadores alinhados aos do tier "básico" em research.md §1 Q2/Q15.
 * Tenants existentes recebem esses defaults (override por plano específico via
 * seed em T118).
 *
 * Modelo Plan permanece compatível — colunas novas têm defaults, nenhum
 * código existente precisa mudar. Trait `HasTenantPlanLimits` (T016) já lida
 * com fallback para os defaults de `config('finalization.*')`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->integer('daily_campaign_limit')
                ->default(200)
                ->after('max_channels')
                ->comment('Q2 — Limite de envio diário de campanha (Lote C). Default: tier básico 200.');

            $table->integer('api_rate_limit_per_minute')
                ->default(100)
                ->after('daily_campaign_limit')
                ->comment('Q15 — Rate limit por minuto na API pública v1 (Lote D). Default: tier básico 100.');

            $table->integer('webhook_max_endpoints')
                ->default(5)
                ->after('api_rate_limit_per_minute')
                ->comment('Q AC-11.1.1 — Limite de endpoints de webhook por tenant (Lote D). Default: tier básico 5.');
        });
    }

    public function down(): void
    {
        Schema::table('plans', function (Blueprint $table): void {
            $table->dropColumn(['daily_campaign_limit', 'api_rate_limit_per_minute', 'webhook_max_endpoints']);
        });
    }
};

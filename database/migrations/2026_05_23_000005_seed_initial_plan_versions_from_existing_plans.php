<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * **T118 (Fase 8 — Lote B US-12.2)** — Bootstrapping de `plan_versions` e `tenant_plan_bindings`
 * a partir dos `plans` e `tenants` existentes.
 *
 * Estratégia:
 *   1. Para cada `Plan` existente: cria `PlanVersion` v=1 com snapshot completo
 *      (incluindo as 3 colunas novas — daily_campaign_limit, api_rate_limit,
 *      webhook_max_endpoints).
 *   2. Para cada `Tenant` existente com `plan_id`: cria `TenantPlanBinding`
 *      vigente apontando para a v1 do plano correspondente.
 *
 * **Idempotência**: a migration USA `INSERT ... ON CONFLICT DO NOTHING` ou
 * checagem prévia para tolerar re-execução em DBs já populadas. Em prática,
 * usa `existsByCondition` em PHP — funciona em qualquer driver.
 *
 * Tenants sem `plan_id` (raro — Fase 0 cria tenant com plano default) são
 * IGNORADOS — devem ser corrigidos manualmente pelo Super Admin via Filament.
 */
return new class extends Migration
{
    public function up(): void
    {
        $now = Carbon::now();

        // 1. PlanVersion v=1 para cada plano.
        $plans = DB::table('plans')->get();

        foreach ($plans as $plan) {
            $alreadyVersioned = DB::table('plan_versions')
                ->where('plan_id', $plan->id)
                ->exists();

            if ($alreadyVersioned) {
                continue;
            }

            $snapshot = [
                'code' => $plan->code,
                'name' => $plan->name,
                'description' => $plan->description,
                'base_price_cents' => (int) $plan->base_price_cents,
                'included_professionals' => (int) $plan->included_professionals,
                'included_ai_messages' => (int) $plan->included_ai_messages,
                'overage_price_cents' => (int) $plan->overage_price_cents,
                'max_users' => (int) $plan->max_users,
                'max_channels' => (int) $plan->max_channels,
                'daily_campaign_limit' => (int) ($plan->daily_campaign_limit ?? 200),
                'api_rate_limit_per_minute' => (int) ($plan->api_rate_limit_per_minute ?? 100),
                'webhook_max_endpoints' => (int) ($plan->webhook_max_endpoints ?? 5),
                'stripe_price_id_base' => $plan->stripe_price_id_base,
                'stripe_price_id_overage' => $plan->stripe_price_id_overage,
                'features_enabled' => [], // populado conforme tier em fase futura
            ];

            DB::table('plan_versions')->insert([
                'plan_id' => $plan->id,
                'version' => 1,
                'valid_from' => $now,
                'valid_to' => null,
                'snapshot' => json_encode($snapshot),
                'created_by_user_id' => null, // seed
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }

        // 2. TenantPlanBinding para cada tenant existente.
        $bindings = DB::table('tenants')
            ->whereNotNull('plan_id')
            ->get(['id', 'plan_id', 'created_at']);

        foreach ($bindings as $tenant) {
            $alreadyBound = DB::table('tenant_plan_bindings')
                ->where('tenant_id', $tenant->id)
                ->whereNull('effective_to')
                ->exists();

            if ($alreadyBound) {
                continue;
            }

            $planVersion = DB::table('plan_versions')
                ->where('plan_id', $tenant->plan_id)
                ->whereNull('valid_to')
                ->first();

            if ($planVersion === null) {
                continue; // plano sem versão (não deveria ocorrer após passo 1)
            }

            DB::table('tenant_plan_bindings')->insert([
                'tenant_id' => $tenant->id,
                'plan_version_id' => $planVersion->id,
                'effective_from' => $tenant->created_at ?? $now,
                'effective_to' => null,
                'changed_by_user_id' => null,
                'change_reason' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        // Não fazemos rollback de dados — migration é idempotente, não destrutiva.
        // Para reverter, derrubar as tabelas plan_versions/tenant_plan_bindings
        // via migrations anteriores resolve.
    }
};

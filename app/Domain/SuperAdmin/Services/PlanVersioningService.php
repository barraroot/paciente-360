<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Services;

use App\Domain\SuperAdmin\Events\PlanoAlteradoPeloSuperAdmin;
use App\Domain\SuperAdmin\Events\PlanoCriado;
use App\Domain\SuperAdmin\Events\PlanoEditado;
use App\Domain\SuperAdmin\Models\PlanVersion;
use App\Domain\SuperAdmin\Models\TenantPlanBinding;
use App\Models\Plan;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * **T120 (Fase 8 — Lote B US-12.2)** — Gerencia o ciclo de vida das versões de plano.
 *
 * Operações:
 *   - `createVersion(plan, snapshot, user)`: edição do catálogo (Q12.2.2 snapshot
 *     versioning). Fecha a versão anterior e cria nova.
 *   - `migrateTenantToPlanVersion(tenant, plan_version, user, reason)`:
 *     migração de um tenant entre versões (AC-12.2.3 proration Stripe).
 *
 * **Stripe proration**: a integração concreta com Cashier (já existente na
 * Fase 0) é DEFERRED para quando primeira migração ocorrer em produção.
 * Por enquanto, este serviço apenas LOGA a intenção e atualiza as bindings.
 * O service provider da Fase 0 deve registrar listener no
 * `PlanoAlteradoPeloSuperAdmin` para acionar `$tenant->updateSubscription()`.
 */
final class PlanVersioningService
{
    /**
     * Cria a próxima versão de um plano (catálogo editado).
     *
     * Fecha a versão atual (valid_to = now()) e insere nova com versão+1.
     * Tenants vinculados à versão ANTERIOR permanecem inalterados (Q12.2.2).
     *
     * @param  array<string, mixed>  $snapshot
     */
    public function createVersion(Plan $plan, array $snapshot, ?User $createdBy = null): PlanVersion
    {
        return DB::transaction(function () use ($plan, $snapshot, $createdBy): PlanVersion {
            $now = Carbon::now();

            $currentActive = PlanVersion::query()
                ->forPlan($plan->id)
                ->active()
                ->lockForUpdate()
                ->first();

            $nextVersion = ($currentActive?->version ?? 0) + 1;

            // Fecha a versão atual (se houver).
            if ($currentActive instanceof PlanVersion) {
                $currentActive->update(['valid_to' => $now]);
            }

            $newVersion = PlanVersion::query()->create([
                'plan_id' => $plan->id,
                'version' => $nextVersion,
                'valid_from' => $now,
                'valid_to' => null,
                'snapshot' => $snapshot,
                'created_by_user_id' => $createdBy?->id,
            ]);

            if ($currentActive === null) {
                Event::dispatch(new PlanoCriado(
                    planId: $plan->id,
                    planVersionId: $newVersion->id,
                    version: $nextVersion,
                    planName: $snapshot['name'] ?? $plan->name,
                    createdByUserId: $createdBy?->id,
                    createdAt: $now,
                ));
            } else {
                Event::dispatch(new PlanoEditado(
                    planId: $plan->id,
                    oldVersion: $currentActive->version,
                    newVersion: $nextVersion,
                    oldVersionId: $currentActive->id,
                    newVersionId: $newVersion->id,
                    changedByUserId: $createdBy?->id,
                ));
            }

            return $newVersion;
        });
    }

    /**
     * Migra um tenant específico de sua versão atual para uma nova.
     *
     * Fecha o `TenantPlanBinding` atual (effective_to=now()) e cria novo.
     * Emite `PlanoAlteradoPeloSuperAdmin` que deve disparar proration Stripe
     * via listener (DEFERRED para Fase 0).
     *
     * @throws InvalidArgumentException se reason < 10 chars (gate AC-12.2.3).
     */
    public function migrateTenantToPlanVersion(
        Tenant $tenant,
        PlanVersion $newPlanVersion,
        User $changedBy,
        string $reason,
    ): TenantPlanBinding {
        if (strlen(trim($reason)) < 10) {
            throw new InvalidArgumentException('Motivo da alteração deve ter no mínimo 10 caracteres.');
        }

        return DB::transaction(function () use ($tenant, $newPlanVersion, $changedBy, $reason): TenantPlanBinding {
            $now = Carbon::now();

            $current = TenantPlanBinding::query()
                ->forTenant($tenant->id)
                ->active()
                ->lockForUpdate()
                ->first();

            $oldPlanVersionId = $current?->plan_version_id;

            // Fecha o vínculo atual.
            if ($current instanceof TenantPlanBinding) {
                $current->update(['effective_to' => $now]);
            }

            $newBinding = TenantPlanBinding::query()->create([
                'tenant_id' => $tenant->id,
                'plan_version_id' => $newPlanVersion->id,
                'effective_from' => $now,
                'effective_to' => null,
                'changed_by_user_id' => $changedBy->id,
                'change_reason' => trim($reason),
            ]);

            // Sincroniza o `tenants.plan_id` com o plano da nova versão (compat com Fase 0).
            $tenant->update(['plan_id' => $newPlanVersion->plan_id]);

            if ($oldPlanVersionId !== null) {
                Event::dispatch(new PlanoAlteradoPeloSuperAdmin(
                    tenantId: $tenant->id,
                    oldPlanVersionId: $oldPlanVersionId,
                    newPlanVersionId: $newPlanVersion->id,
                    changedByUserId: $changedBy->id,
                    effectiveAt: $now,
                    reason: trim($reason),
                ));

                // DEFERRED — proration Stripe via Cashier seria invocado aqui ou
                // por listener; por enquanto apenas log estruturado.
                Log::info('super_admin.plan.tenant_migrated_pending_stripe_proration', [
                    'tenant_id' => $tenant->id,
                    'old_plan_version_id' => $oldPlanVersionId,
                    'new_plan_version_id' => $newPlanVersion->id,
                    'changed_by_user_id' => $changedBy->id,
                ]);
            }

            return $newBinding;
        });
    }

    /**
     * Retorna a versão ATIVA do plano. Útil para queries que precisam do
     * snapshot vigente sem expor lógica de PARTIAL UNIQUE.
     */
    public function activeVersionFor(Plan $plan): ?PlanVersion
    {
        return PlanVersion::query()->forPlan($plan->id)->active()->first();
    }

    /**
     * Retorna a binding vigente de um tenant.
     */
    public function activeBindingFor(Tenant $tenant): ?TenantPlanBinding
    {
        return TenantPlanBinding::query()->forTenant($tenant->id)->active()->first();
    }
}

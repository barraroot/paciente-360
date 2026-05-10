<?php

namespace Tests\Concerns;

use App\Models\Tenant;
use App\Models\User;

/**
 * Helpers para suítes de teste do Lote B (Phase 2.2 — multi-tenant
 * infra). Centraliza criação de tenants/users e simula a resolução do
 * middleware `ResolveTenant` em cenários de teste sem precisar bater no
 * subdomínio real.
 *
 * Uso:
 *
 *     use RefreshDatabase, CreatesTenants;
 *
 *     $tenantA = $this->createTenant(['slug' => 'clinica-a']);
 *     $userA   = $this->createUserForTenant($tenantA);
 *     $this->actingAsUserOfTenant($tenantA);
 */
trait CreatesTenants
{
    /**
     * Cria um tenant com a TenantFactory; aceita `overrides` para slug,
     * cnpj, status etc.
     *
     * @param array<string, mixed> $overrides
     */
    protected function createTenant(array $overrides = []): Tenant
    {
        return Tenant::factory()->create($overrides);
    }

    /**
     * Cria um User vinculado ao tenant indicado. Use este caminho — o
     * factory default cria User SEM tenant (Super Admin), o que não é o
     * cenário típico das features de domínio.
     *
     * @param array<string, mixed> $overrides
     */
    protected function createUserForTenant(Tenant $tenant, array $overrides = []): User
    {
        return User::factory()->forTenant($tenant)->create($overrides);
    }

    /**
     * Autentica como usuário do tenant informado e injeta o tenant no
     * container (substituto do `ResolveTenant` em testes que não passam
     * pelo HTTP layer).
     *
     * Em testes feature que batem em rota (subdomínio `lvh.me`), o
     * próprio middleware fará o bind — neste caso, basta usar
     * `$this->actingAs($user)`.
     */
    protected function actingAsUserOfTenant(Tenant $tenant, ?User $user = null): self
    {
        $user ??= $this->createUserForTenant($tenant);

        $this->app->instance('tenant', $tenant);
        $this->actingAs($user);

        return $this;
    }

    /**
     * Helper de assertion: bate em uma URL de outro tenant e exige
     * resposta 403/404 (vazamento de tenant não é permitido — Princípio
     * II).
     */
    protected function assertCannotAccessTenant(Tenant $other, string $url): void
    {
        $response = $this->getJson($url, [
            'Host' => $other->slug.'.'.config('tenancy.subdomain_suffix'),
        ]);

        $this->assertContains(
            $response->getStatusCode(),
            [403, 404],
            "Esperado 403/404 ao acessar {$url} no tenant {$other->slug}; obteve {$response->getStatusCode()}."
        );
    }
}

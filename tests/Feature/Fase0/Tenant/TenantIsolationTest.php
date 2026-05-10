<?php

namespace Tests\Feature\Fase0\Tenant;

use App\Models\AuditLog;
use App\Models\Invitation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * **CI gate** do Lote B (T040). Cresce a cada PR que introduz nova rota
 * autenticada — toda feature de domínio deve provar que tenant A não
 * enxerga dado de tenant B (Princípio II — tenant isolation by default).
 *
 * Estratégia:
 *  - Usa subdomínios `*.lvh.me` (DNS público que resolve em 127.0.0.1)
 *    como host do request — mesmo code path do middleware
 *    `ResolveTenant` em produção.
 *  - Rota dummy `/api/v1/_ping` em `routes/api.php` retorna o slug do
 *    tenant resolvido; nos próximos lotes, será substituída por rotas
 *    reais. O teste de isolamento real cresce conforme as rotas surgem.
 */
class TenantIsolationTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    public function test_resolve_tenant_returns_404_for_unknown_subdomain(): void
    {
        $response = $this->getJson('http://nope.lvh.me/api/v1/_ping');

        $response->assertNotFound();
    }

    public function test_resolve_tenant_returns_tenant_for_existing_subdomain(): void
    {
        $tenant = $this->createTenant(['slug' => 'clinica-x']);

        $response = $this->getJson('http://clinica-x.lvh.me/api/v1/_ping');

        $response->assertOk();
        $this->assertSame($tenant->slug, $response->getContent());
    }

    public function test_public_subdomain_does_not_require_tenant(): void
    {
        // `crm.lvh.me` está em `config('tenancy.public_hosts')` — bypass
        // total do middleware. Como `routes/api.php` exige tenant para
        // `_ping`, esperamos 500/exception ou 200 com null. Para esta
        // fase, apenas garantimos que NÃO retorna 404 (público é válido,
        // mesmo sem rota concreta).
        $response = $this->getJson('http://crm.lvh.me/api/v1/_ping');

        $this->assertNotSame(404, $response->getStatusCode(), 'Hosts públicos não devem cair no 404 do tenant inexistente.');
    }

    /**
     * Testa que factories segregam tenant_id por padrão.
     * Cada factory cria modelos com tenant_id específico.
     */
    public function test_two_tenants_have_isolated_users(): void
    {
        $tenantA = $this->createTenant(['slug' => 'tenant-a']);
        $tenantB = $this->createTenant(['slug' => 'tenant-b']);

        $userA = $this->createUserForTenant($tenantA);
        $userB = $this->createUserForTenant($tenantB);

        $this->assertSame($tenantA->id, $userA->tenant_id);
        $this->assertSame($tenantB->id, $userB->tenant_id);
        $this->assertNotSame($userA->tenant_id, $userB->tenant_id);
    }

    /**
     * Testa que cada tenant tem invitations segregadas.
     * Factories criam Invitation com tenant_id específico.
     */
    public function test_invitations_are_segregated_by_tenant(): void
    {
        $tenantA = $this->createTenant(['slug' => 'clinica-a']);
        $tenantB = $this->createTenant(['slug' => 'clinica-b']);

        $invA = Invitation::factory()
            ->for($tenantA)
            ->create();
        $invB = Invitation::factory()
            ->for($tenantB)
            ->create();

        $this->assertSame($tenantA->id, $invA->tenant_id);
        $this->assertSame($tenantB->id, $invB->tenant_id);
        $this->assertNotSame($invA->tenant_id, $invB->tenant_id);
    }

    /**
     * Testa que cada tenant tem seus próprios audit logs.
     * AuditLog cria registros com tenant_id específico.
     */
    public function test_audit_logs_are_segregated_by_tenant(): void
    {
        $tenantA = $this->createTenant(['slug' => 'clinica-a']);
        $tenantB = $this->createTenant(['slug' => 'clinica-b']);

        $logA = AuditLog::create([
            'tenant_id' => $tenantA->id,
            'user_id' => null,
            'actor_type' => 'system',
            'action' => 'test.event',
            'auditable_type' => 'test',
            'auditable_id' => 1,
            'payload' => [],
        ]);

        $logB = AuditLog::create([
            'tenant_id' => $tenantB->id,
            'user_id' => null,
            'actor_type' => 'system',
            'action' => 'test.event',
            'auditable_type' => 'test',
            'auditable_id' => 1,
            'payload' => [],
        ]);

        $this->assertSame($tenantA->id, $logA->tenant_id);
        $this->assertSame($tenantB->id, $logB->tenant_id);
    }
}

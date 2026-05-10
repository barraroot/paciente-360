<?php

namespace Tests\Feature\Fase0\Tenant;

use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\CreatesTenants;
use Tests\TestCase;

/**
 * **T144** — endpoint público GET `/tenant` resolvido pelo subdomínio.
 *
 * Retorna metadados do tenant ativo para o SPA carregar branding/status
 * antes de qualquer login. Não expõe campos sensíveis (CNPJ, contatos
 * do responsável, stripe_customer_id) — Princípio I (LGPD minimização).
 *
 * @see specs/001-fundacao-multitenant/contracts/openapi.yaml § /tenant
 * @see specs/001-fundacao-multitenant/tasks.md — T144
 */
class CurrentTenantTest extends TestCase
{
    use CreatesTenants;
    use RefreshDatabase;

    private function tenantUrl(string $slug): string
    {
        return "http://{$slug}.lvh.me/api/v1/tenant";
    }

    public function test_returns_tenant_for_existing_subdomain(): void
    {
        $tenant = $this->createTenant([
            'slug' => 'clinica-x',
            'name' => 'Clínica X',
            'status' => 'trial',
            'trial_ends_at' => now()->addDays(14),
        ]);

        $response = $this->getJson($this->tenantUrl('clinica-x'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'id', 'slug', 'name', 'status', 'trial_ends_at',
                'plan', 'restrictions_active', 'onboarding_completed',
            ],
        ]);
        $response->assertJsonPath('data.id', $tenant->id);
        $response->assertJsonPath('data.slug', 'clinica-x');
        $response->assertJsonPath('data.name', 'Clínica X');
        $response->assertJsonPath('data.status', 'trial');
        $response->assertJsonPath('data.restrictions_active', false);
        $response->assertJsonPath('data.onboarding_completed', false);
        $response->assertJsonPath('data.plan', null);
    }

    public function test_returns_404_for_unknown_subdomain(): void
    {
        $response = $this->getJson($this->tenantUrl('inexistente'));

        $response->assertNotFound();
    }

    public function test_includes_plan_when_attached(): void
    {
        // Cria plano direto via seeder não basta: precisamos garantir
        // anexar a um tenant. Inserção mínima sem depender de Plan model
        // (que é criado no T144).
        $planId = DB::table('plans')->insertGetId([
            'code' => 'test-pro',
            'name' => 'Test Pro',
            'description' => null,
            'base_price_cents' => 49900,
            'included_professionals' => 5,
            'included_ai_messages' => 50000,
            'overage_price_cents' => 5,
            'max_users' => 20,
            'max_channels' => 3,
            'stripe_price_id_base' => 'price_test_base',
            'stripe_price_id_overage' => 'price_test_overage',
            'is_active' => true,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $tenant = $this->createTenant([
            'slug' => 'clinica-pro',
            'plan_id' => $planId,
        ]);

        $response = $this->getJson($this->tenantUrl('clinica-pro'));

        $response->assertOk();
        $response->assertJsonPath('data.plan.id', $planId);
        $response->assertJsonPath('data.plan.code', 'test-pro');
        $response->assertJsonPath('data.plan.name', 'Test Pro');
        $response->assertJsonPath('data.plan.is_active', true);

        $this->assertSame($tenant->id, $response->json('data.id'));
    }

    public function test_omits_sensitive_fields(): void
    {
        $this->createTenant([
            'slug' => 'clinica-safe',
            'cnpj' => '11222333000181',
            'responsible_name' => 'Pessoa Privada',
            'responsible_email' => 'privado@clinica.com',
            'responsible_phone' => '+5511999999999',
            'stripe_customer_id' => 'cus_test_xyz',
        ]);

        $response = $this->getJson($this->tenantUrl('clinica-safe'));

        $response->assertOk();

        $payload = $response->json('data');
        $this->assertIsArray($payload);
        $this->assertArrayNotHasKey('cnpj', $payload);
        $this->assertArrayNotHasKey('responsible_name', $payload);
        $this->assertArrayNotHasKey('responsible_email', $payload);
        $this->assertArrayNotHasKey('responsible_phone', $payload);
        $this->assertArrayNotHasKey('stripe_customer_id', $payload);

        // Sanity: dados mínimos PRESENTES (id, slug, name, status).
        $this->assertSame('clinica-safe', $payload['slug']);
    }

    public function test_restrictions_active_reflects_overdue_state(): void
    {
        $this->createTenant([
            'slug' => 'clinica-overdue',
            'status' => 'overdue',
            'restrictions_applied_at' => now()->subDay(),
        ]);

        $response = $this->getJson($this->tenantUrl('clinica-overdue'));

        $response->assertOk();
        $response->assertJsonPath('data.restrictions_active', true);
        $response->assertJsonPath('data.status', 'overdue');

        $this->assertInstanceOf(Tenant::class, app('tenant'));
    }
}

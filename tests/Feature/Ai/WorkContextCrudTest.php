<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * Feature 017 (US2) — T017: CRUD singleton do Contexto de Trabalho, versão
 * incremental, allow-list não-clínica e isolamento por tenant (FR-007/012).
 */
final class WorkContextCrudTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->user] = $this->tenantAndUserForRole('wc-a', 'admin-clinica');
    }

    /**
     * @return array<string, string>
     */
    private function headers(Tenant $tenant): array
    {
        return ['X-Tenant-Slug' => $tenant->slug];
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(string $price = 'R$300'): array
    {
        return [
            'services' => [['nome' => 'Consulta enxaqueca', 'descricao' => 'Avaliação ~1h']],
            'pricing' => [['item' => 'Consulta', 'valor_a_vista' => $price, 'valor_cartao' => 'R$330']],
            'locations' => [['cidade' => 'Aracaju']],
            'deposit_policy' => ['exige_sinal' => true, 'percentual' => 20, 'meio' => 'PIX'],
            'tone' => 'acolhedor, com emojis',
            'qualification_questions' => ['Com que frequência?', 'Atrapalha sua rotina?'],
            'free_form' => 'Avaliação cuidadosa e individualizada.',
        ];
    }

    public function test_get_returns_empty_default_when_unconfigured(): void
    {
        $this->withHeaders($this->headers($this->tenant))
            ->getJson($this->tenantUrl($this->tenant, '/ai/work-context'))
            ->assertOk()
            ->assertJsonPath('data.version', null)
            ->assertJsonPath('data.services', []);
    }

    public function test_put_creates_then_increments_version(): void
    {
        $this->withHeaders($this->headers($this->tenant))
            ->putJson($this->tenantUrl($this->tenant, '/ai/work-context'), $this->validPayload())
            ->assertOk()
            ->assertJsonPath('data.version', 1)
            ->assertJsonPath('data.pricing.0.valor_a_vista', 'R$300');

        $this->withHeaders($this->headers($this->tenant))
            ->putJson($this->tenantUrl($this->tenant, '/ai/work-context'), $this->validPayload('R$350'))
            ->assertOk()
            ->assertJsonPath('data.version', 2);

        $this->assertDatabaseHas('ai_work_contexts', [
            'tenant_id' => $this->tenant->id,
            'version' => 2,
        ]);
        $this->assertDatabaseCount('ai_work_contexts', 1);
    }

    public function test_rejects_non_clinical_allow_list_violation(): void
    {
        $payload = $this->validPayload();
        $payload['diagnostico'] = 'hipertensão';

        $this->withHeaders($this->headers($this->tenant))
            ->putJson($this->tenantUrl($this->tenant, '/ai/work-context'), $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors('diagnostico');
    }

    public function test_is_isolated_per_tenant(): void
    {
        // Tenant A configura.
        $this->withHeaders($this->headers($this->tenant))
            ->putJson($this->tenantUrl($this->tenant, '/ai/work-context'), $this->validPayload('R$300'))
            ->assertOk();

        // Tenant B (outro usuário/admin) não enxerga o contexto de A.
        [$tenantB] = $this->tenantAndUserForRole('wc-b', 'admin-clinica');

        $this->withHeaders($this->headers($tenantB))
            ->getJson($this->tenantUrl($tenantB, '/ai/work-context'))
            ->assertOk()
            ->assertJsonPath('data.version', null)
            ->assertJsonPath('data.pricing', []);
    }
}

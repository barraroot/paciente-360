<?php

namespace Tests\Feature\Fase3\US5_Assignment;

use App\Domain\Messaging\Assignment\Models\AssignmentRule;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T143 — Feature tests para GET/PUT /api/v1/inbox/assignment-rules.
 *
 * Cobre AC-4.5.2: admin gerencia regras de auto-atribuição.
 */
class AssignmentRulesCrudTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-rules', 'admin-clinica');
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    #[Test]
    public function admin_can_list_active_rules_ordered_by_priority(): void
    {
        AssignmentRule::factory()
            ->forTenant($this->tenant)
            ->state(['strategy' => 'patient_owner', 'priority' => 10, 'is_active' => true])
            ->create();

        AssignmentRule::factory()
            ->forTenant($this->tenant)
            ->state(['strategy' => 'round_robin', 'priority' => 20, 'is_active' => true])
            ->create();

        AssignmentRule::factory()
            ->forTenant($this->tenant)
            ->state(['strategy' => 'manual', 'priority' => 5, 'is_active' => false])
            ->create();

        $response = $this->getJson($this->baseUrl('/inbox/assignment-rules'));

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => ['id', 'strategy', 'priority', 'is_active', 'config'],
            ],
        ]);

        // Only active, ordered by priority
        $data = $response->json('data');
        $this->assertCount(2, $data);
        $this->assertEquals('patient_owner', $data[0]['strategy']); // priority 10
        $this->assertEquals('round_robin', $data[1]['strategy']);   // priority 20
    }

    #[Test]
    public function admin_can_replace_full_rules_set_with_put(): void
    {
        // Existing rule
        AssignmentRule::factory()
            ->forTenant($this->tenant)
            ->state(['strategy' => 'manual', 'priority' => 100, 'is_active' => true])
            ->create();

        $response = $this->putJson($this->baseUrl('/inbox/assignment-rules'), [
            'rules' => [
                [
                    'strategy' => 'patient_owner',
                    'priority' => 10,
                    'is_active' => true,
                    'config' => [],
                    'channel_id' => null,
                ],
                [
                    'strategy' => 'round_robin',
                    'priority' => 20,
                    'is_active' => true,
                    'config' => [],
                    'channel_id' => null,
                ],
            ],
        ]);

        $response->assertOk();

        // Old rule gone, 2 new rules
        $this->assertEquals(2, AssignmentRule::where('tenant_id', $this->tenant->id)->count());
        $this->assertDatabaseHas('messaging_assignment_rules', [
            'tenant_id' => $this->tenant->id,
            'strategy' => 'patient_owner',
            'priority' => 10,
        ]);
    }

    #[Test]
    public function attendant_without_admin_role_cannot_modify_rules(): void
    {
        $atendente = $this->userForRole($this->tenant, 'atendente');
        Sanctum::actingAs($atendente, ['*']);
        $this->app->instance('tenant', $this->tenant);

        $response = $this->putJson($this->baseUrl('/inbox/assignment-rules'), [
            'rules' => [],
        ]);

        $response->assertForbidden();
    }

    #[Test]
    public function rules_validate_strategy_enum(): void
    {
        $response = $this->putJson($this->baseUrl('/inbox/assignment-rules'), [
            'rules' => [
                [
                    'strategy' => 'invalid_strategy',
                    'priority' => 10,
                    'is_active' => true,
                    'config' => [],
                ],
            ],
        ]);

        $response->assertUnprocessable();
        $response->assertJsonValidationErrors(['rules.0.strategy']);
    }

    #[Test]
    public function rules_can_be_scoped_to_specific_channel_or_global(): void
    {
        $channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $response = $this->putJson($this->baseUrl('/inbox/assignment-rules'), [
            'rules' => [
                [
                    'strategy' => 'round_robin',
                    'priority' => 10,
                    'is_active' => true,
                    'config' => [],
                    'channel_id' => $channel->id, // channel-scoped
                ],
                [
                    'strategy' => 'patient_owner',
                    'priority' => 20,
                    'is_active' => true,
                    'config' => [],
                    'channel_id' => null, // global
                ],
            ],
        ]);

        $response->assertOk();
        $this->assertDatabaseHas('messaging_assignment_rules', [
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
            'strategy' => 'round_robin',
        ]);
        $this->assertDatabaseHas('messaging_assignment_rules', [
            'tenant_id' => $this->tenant->id,
            'channel_id' => null,
            'strategy' => 'patient_owner',
        ]);
    }

    #[Test]
    public function setting_priority_changes_evaluation_order(): void
    {
        $response = $this->putJson($this->baseUrl('/inbox/assignment-rules'), [
            'rules' => [
                ['strategy' => 'round_robin', 'priority' => 5, 'is_active' => true, 'config' => []],
                ['strategy' => 'patient_owner', 'priority' => 1, 'is_active' => true, 'config' => []],
            ],
        ]);

        $response->assertOk();

        // List returns in priority order (ascending = lower = higher priority)
        $listResponse = $this->getJson($this->baseUrl('/inbox/assignment-rules'));
        $data = $listResponse->json('data');

        $this->assertEquals('patient_owner', $data[0]['strategy']); // priority 1 first
        $this->assertEquals('round_robin', $data[1]['strategy']);    // priority 5 second
    }

    #[Test]
    public function isolated_per_tenant(): void
    {
        $tenantB = $this->bootstrapTenantWithRoles('clinica-rules-beta');
        $adminB = $this->userForRole($tenantB, 'admin-clinica');

        // Admin B creates rule for Tenant B
        Sanctum::actingAs($adminB, ['*']);
        $this->app->instance('tenant', $tenantB);

        $this->putJson($this->tenantUrl($tenantB, '/inbox/assignment-rules'), [
            'rules' => [
                ['strategy' => 'manual', 'priority' => 100, 'is_active' => true, 'config' => []],
            ],
        ])->assertOk();

        // Admin A only sees their own rules (none)
        Sanctum::actingAs($this->admin, ['*']);
        $this->app->instance('tenant', $this->tenant);

        $response = $this->getJson($this->baseUrl('/inbox/assignment-rules'));
        $response->assertOk();
        $response->assertJsonCount(0, 'data');
    }
}

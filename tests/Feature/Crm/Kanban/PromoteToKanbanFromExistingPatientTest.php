<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Kanban;

use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Permission;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * T123 — REST endpoint to manually promote existing patient to kanban (FR-019, US3).
 *
 * Verifica que um user com permission `paciente.update` pode chamar
 * POST /api/v1/pacientes/{paciente}/promote-to-kanban para mover um
 * paciente regular para o kanban como novo lead.
 */
final class PromoteToKanbanFromExistingPatientTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private User $user;

    private Paciente $paciente;

    private FunilColuna $newColumn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        // Criar coluna inicial
        $this->newColumn = FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Novos',
            'slug' => 'new',
            'posicao' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        // Criar user com permission
        $this->user = User::factory()->create(['tenant_id' => $this->tenant->id]);
        $this->setupPermissions();

        // Criar paciente regular (não-lead)
        $paciente = Paciente::make([
            'nome' => 'Paciente Ativo',
            'status' => 'ativo',
            'telefone_primario' => '+5511988888888',
            'funil_coluna_atual_id' => null,
        ])->forceFill(['tenant_id' => $this->tenant->id]);
        $paciente->save();
        $this->paciente = $paciente;
    }

    private function setupPermissions(): void
    {
        // Setup Spatie permissions
        $this->user->givePermissionTo(Permission::firstOrCreate([
            'name' => 'paciente.update',
            'guard_name' => 'web',
            'tenant_id' => $this->tenant->id,
        ]));
    }

    /**
     * @test
     */
    public function test_promote_existing_patient_to_kanban(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson(
            "/api/v1/pacientes/{$this->paciente->id}/promote-to-kanban",
            [
                'funil_coluna_id' => $this->newColumn->id,
                'reason' => 'Procedimento novo',
            ],
            ['X-Tenant-Slug' => $this->tenant->slug]
        );

        $response->assertStatus(201);
        $responseData = $response->json('data');
        $this->assertArrayHasKey('paciente_id', $responseData);
        $this->assertArrayHasKey('curation_event_id', $responseData);

        // Verify: paciente foi movido para o kanban
        $this->paciente->refresh();
        $this->assertEquals($this->newColumn->id, $this->paciente->funil_coluna_atual_id);

        // Verify: KanbanCurationEvent foi criado com source='user'
        $event = KanbanCurationEvent::where('tenant_id', $this->tenant->id)
            ->where('paciente_id', $this->paciente->id)
            ->where('event_kind', 'manual_promoted_to_kanban')
            ->first();
        $this->assertNotNull($event);
        $this->assertEquals('user', $event->actor_type);
        $this->assertEquals($this->user->id, $event->actor_id);
        $this->assertEquals('Procedimento novo', $event->reason);
    }

    /**
     * @test
     */
    public function test_promote_without_permission_returns_forbidden(): void
    {
        // Criar user sem permission
        $unprivilegedUser = User::factory()->create(['tenant_id' => $this->tenant->id]);
        Sanctum::actingAs($unprivilegedUser, ['*']);

        $response = $this->postJson(
            "/api/v1/pacientes/{$this->paciente->id}/promote-to-kanban",
            [
                'funil_coluna_id' => $this->newColumn->id,
                'reason' => 'Test',
            ],
            ['X-Tenant-Slug' => $this->tenant->slug]
        );

        $response->assertStatus(403);

        // Verify: paciente não foi alterado
        $this->paciente->refresh();
        $this->assertNull($this->paciente->funil_coluna_atual_id);
    }

    /**
     * @test
     */
    public function test_promote_with_invalid_coluna_returns_422(): void
    {
        Sanctum::actingAs($this->user, ['*']);

        $invalidColunaId = 99999;
        $response = $this->postJson(
            "/api/v1/pacientes/{$this->paciente->id}/promote-to-kanban",
            [
                'funil_coluna_id' => $invalidColunaId,
                'reason' => 'Test',
            ],
            ['X-Tenant-Slug' => $this->tenant->slug]
        );

        // Validation error for non-existent coluna
        $response->assertStatus(422);
        $this->assertArrayHasKey('funil_coluna_id', $response->json('errors'));
    }

    /**
     * @test
     */
    public function test_promote_cross_tenant_returns_404(): void
    {
        // Criar outro tenant
        $otherTenant = Tenant::factory()->create();

        // Criar paciente no outro tenant
        $otherPaciente = Paciente::make([
            'nome' => 'Outro Paciente',
            'status' => 'ativo',
            'telefone_primario' => '+5511977777777',
        ])->forceFill(['tenant_id' => $otherTenant->id]);
        $otherPaciente->save();

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson(
            "/api/v1/pacientes/{$otherPaciente->id}/promote-to-kanban",
            [
                'funil_coluna_id' => $this->newColumn->id,
                'reason' => 'Test',
            ],
            ['X-Tenant-Slug' => $this->tenant->slug]
        );

        // Deve retornar 404 pois o paciente não pertence a este tenant
        $response->assertStatus(404);
    }

    /**
     * @test
     */
    public function test_promote_to_terminal_column_returns_409(): void
    {
        // Criar coluna terminal
        $terminalColumn = FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Perdido',
            'slug' => 'perdido',
            'posicao' => 10,
            'is_initial' => false,
            'is_terminal' => true,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        // Criar uma segunda coluna não-terminal onde o paciente já está
        $existingColumn = FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Em Progresso',
            'slug' => 'em_progresso',
            'posicao' => 5,
            'is_initial' => false,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        // Mover paciente para coluna existente primeiro
        $this->paciente->update(['funil_coluna_atual_id' => $existingColumn->id]);

        Sanctum::actingAs($this->user, ['*']);

        $response = $this->postJson(
            "/api/v1/pacientes/{$this->paciente->id}/promote-to-kanban",
            [
                'funil_coluna_id' => $terminalColumn->id,
                'reason' => 'Test',
            ],
            ['X-Tenant-Slug' => $this->tenant->slug]
        );

        // Deve retornar 409 pois não se pode promover para coluna terminal a partir de uma coluna não-terminal
        $response->assertStatus(409);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Kanban;

use App\Domain\Crm\Kanban\Models\KanbanPipelineMapping;
use App\Domain\Crm\Kanban\Services\KanbanAutoTransitionService;
use App\Domain\Crm\Kanban\Services\KanbanPipelineMappingService;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T125 — Cross-tenant isolation in pipeline mappings (SC-007, FR-050, US3).
 *
 * Verifica que um mapping em tenant A não aparece para tenant B,
 * e que transições em A não afetam pacientes em B.
 */
final class PipelineMappingCrossTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private FunilColuna $colA1;

    private FunilColuna $colA2;

    private FunilColuna $colB1;

    private FunilColuna $colB2;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup tenant A
        $this->tenantA = Tenant::factory()->create(['name' => 'Clínica A']);
        $this->colA1 = FunilColuna::create([
            'tenant_id' => $this->tenantA->id,
            'nome' => 'Novos A',
            'slug' => 'new',
            'posicao' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'is_system' => true,
        ]);
        $this->colA2 = FunilColuna::create([
            'tenant_id' => $this->tenantA->id,
            'nome' => 'Agendado A',
            'slug' => 'agendado',
            'posicao' => 4,
            'is_initial' => false,
            'is_terminal' => false,
            'is_system' => true,
        ]);

        // Setup tenant B
        $this->tenantB = Tenant::factory()->create(['name' => 'Clínica B']);
        $this->colB1 = FunilColuna::create([
            'tenant_id' => $this->tenantB->id,
            'nome' => 'Novos B',
            'slug' => 'new',
            'posicao' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'is_system' => true,
        ]);
        $this->colB2 = FunilColuna::create([
            'tenant_id' => $this->tenantB->id,
            'nome' => 'Agendado B',
            'slug' => 'agendado',
            'posicao' => 4,
            'is_initial' => false,
            'is_terminal' => false,
            'is_system' => true,
        ]);

        // Setup mapping only for tenant A
        KanbanPipelineMapping::create([
            'tenant_id' => $this->tenantA->id,
            'event_kind' => 'slot_held',
            'funil_coluna_id' => $this->colA2->id,
            'is_active' => true,
        ]);

        // NO mapping for tenant B
    }

    /**
     * @test
     */
    public function test_tenant_b_mapping_not_visible_from_tenant_a(): void
    {
        app()->instance('tenant', $this->tenantA);

        // Query mappings from tenant A
        $mappingA = KanbanPipelineMapping::where('tenant_id', $this->tenantA->id)
            ->where('event_kind', 'slot_held')
            ->first();

        // Must exist for A
        $this->assertNotNull($mappingA);
        $this->assertEquals($this->colA2->id, $mappingA->funil_coluna_id);

        // Verify tenant B's mapping is separate
        $mappingB = KanbanPipelineMapping::where('tenant_id', $this->tenantB->id)
            ->where('event_kind', 'slot_held')
            ->first();

        // Must NOT exist for B
        $this->assertNull($mappingB);
    }

    /**
     * @test
     */
    public function test_transition_in_tenant_a_does_not_affect_tenant_b(): void
    {
        // Create paciente in tenant A
        app()->instance('tenant', $this->tenantA);
        $pacienteA = Paciente::make([
            'nome' => 'Paciente A',
            'status' => 'lead',
            'telefone_primario' => '+5511966666666',
            'funil_coluna_atual_id' => $this->colA1->id,
        ])->forceFill(['tenant_id' => $this->tenantA->id]);
        $pacienteA->save();

        // Create paciente in tenant B (with same phone - intentionally)
        app()->instance('tenant', $this->tenantB);
        $pacienteB = Paciente::make([
            'nome' => 'Paciente B',
            'status' => 'lead',
            'telefone_primario' => '+5511966666666',
            'funil_coluna_atual_id' => $this->colB1->id,
        ])->forceFill(['tenant_id' => $this->tenantB->id]);
        $pacienteB->save();

        // Apply transition to A
        app()->instance('tenant', $this->tenantA);
        $serviceA = app(KanbanAutoTransitionService::class);
        $serviceA->apply($pacienteA, 'slot_held');

        $pacienteA->refresh();
        $pacienteB->refresh();

        // Verify: A was moved
        $this->assertEquals($this->colA2->id, $pacienteA->funil_coluna_atual_id);

        // Verify: B was NOT moved (mapping doesn't exist)
        $this->assertEquals($this->colB1->id, $pacienteB->funil_coluna_atual_id);
    }

    /**
     * @test
     */
    public function test_mapping_isolation_via_service_query(): void
    {
        app()->instance('tenant', $this->tenantA);

        // Query via service (simulated - KanbanPipelineMappingService)
        $service = app(KanbanPipelineMappingService::class);
        $mapping = $service->colunaFor($this->tenantA->id, 'slot_held');

        // Must return A's mapping
        $this->assertNotNull($mapping);
        $this->assertEquals($this->colA2->id, $mapping->id);

        // Query for B (should be null)
        $mappingB = $service->colunaFor($this->tenantB->id, 'slot_held');
        $this->assertNull($mappingB);
    }

    /**
     * @test
     */
    public function test_creating_mapping_in_b_does_not_affect_a(): void
    {
        // Initially B has no mapping
        $initialMapping = KanbanPipelineMapping::where('tenant_id', $this->tenantB->id)
            ->where('event_kind', 'slot_held')
            ->first();
        $this->assertNull($initialMapping);

        // Create mapping for B
        KanbanPipelineMapping::create([
            'tenant_id' => $this->tenantB->id,
            'event_kind' => 'slot_held',
            'funil_coluna_id' => $this->colB2->id,
            'is_active' => true,
        ]);

        // A's mapping should still be the same
        $mappingA = KanbanPipelineMapping::where('tenant_id', $this->tenantA->id)
            ->where('event_kind', 'slot_held')
            ->first();
        $this->assertNotNull($mappingA);
        $this->assertEquals($this->colA2->id, $mappingA->funil_coluna_id);

        // B's mapping should be the new one
        $mappingB = KanbanPipelineMapping::where('tenant_id', $this->tenantB->id)
            ->where('event_kind', 'slot_held')
            ->first();
        $this->assertNotNull($mappingB);
        $this->assertEquals($this->colB2->id, $mappingB->funil_coluna_id);
    }
}

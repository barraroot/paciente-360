<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Kanban;

use App\Domain\Crm\Kanban\Models\KanbanPipelineMapping;
use App\Domain\Crm\Kanban\Services\KanbanAutoTransitionService;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T124 — Pipeline mapping is per-tenant customizable (FR-019, US3).
 *
 * Verifica que um tenant pode customizar o mapeamento de eventos para colunas
 * sem afetar outro tenant.
 */
final class PipelineMappingTenantCustomizationTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenantA;

    private Tenant $tenantB;

    private FunilColuna $colA1;

    private FunilColuna $colA2;

    private FunilColuna $colB1;

    private FunilColuna $colB2Custom;

    protected function setUp(): void
    {
        parent::setUp();

        // Setup tenant A
        $this->tenantA = Tenant::factory()->create(['name' => 'Tenant A']);
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
        $this->tenantB = Tenant::factory()->create(['name' => 'Tenant B']);
        $this->colB1 = FunilColuna::create([
            'tenant_id' => $this->tenantB->id,
            'nome' => 'Novos B',
            'slug' => 'new',
            'posicao' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'is_system' => true,
        ]);
        $this->colB2Custom = FunilColuna::create([
            'tenant_id' => $this->tenantB->id,
            'nome' => 'Custom Estado B',
            'slug' => 'custom_estado',
            'posicao' => 3,
            'is_initial' => false,
            'is_terminal' => false,
            'is_system' => false,
        ]);

        // Setup mappings: Tenant A uses default, Tenant B uses custom
        KanbanPipelineMapping::create([
            'tenant_id' => $this->tenantA->id,
            'event_kind' => 'slot_held',
            'funil_coluna_id' => $this->colA2->id,
            'is_active' => true,
        ]);

        KanbanPipelineMapping::create([
            'tenant_id' => $this->tenantB->id,
            'event_kind' => 'slot_held',
            'funil_coluna_id' => $this->colB2Custom->id,
            'is_active' => true,
        ]);
    }

    /**
     * @test
     */
    public function test_tenant_a_uses_default_mapping(): void
    {
        app()->instance('tenant', $this->tenantA);

        $paciente = Paciente::make([
            'nome' => 'Paciente A',
            'status' => 'lead',
            'telefone_primario' => '+5511911111111',
            'funil_coluna_atual_id' => $this->colA1->id,
        ])->forceFill(['tenant_id' => $this->tenantA->id]);
        $paciente->save();

        $service = app(KanbanAutoTransitionService::class);
        $service->apply($paciente, 'slot_held');

        $paciente->refresh();

        // Verify: moved to colA2 (agendado)
        $this->assertEquals($this->colA2->id, $paciente->funil_coluna_atual_id);
    }

    /**
     * @test
     */
    public function test_tenant_b_uses_custom_mapping(): void
    {
        app()->instance('tenant', $this->tenantB);

        $paciente = Paciente::make([
            'nome' => 'Paciente B',
            'status' => 'lead',
            'telefone_primario' => '+5511922222222',
            'funil_coluna_atual_id' => $this->colB1->id,
        ])->forceFill(['tenant_id' => $this->tenantB->id]);
        $paciente->save();

        $service = app(KanbanAutoTransitionService::class);
        $service->apply($paciente, 'slot_held');

        $paciente->refresh();

        // Verify: moved to colB2Custom (custom estado)
        $this->assertEquals($this->colB2Custom->id, $paciente->funil_coluna_atual_id);
    }

    /**
     * @test
     */
    public function test_mapping_change_affects_only_that_tenant(): void
    {
        // Remove old mapping for Tenant B and create new one
        KanbanPipelineMapping::where('tenant_id', $this->tenantB->id)
            ->where('event_kind', 'slot_held')
            ->delete();

        // Create new mapping
        $newColB = FunilColuna::create([
            'tenant_id' => $this->tenantB->id,
            'nome' => 'Novo Mapeamento B',
            'slug' => 'novo_mapeamento',
            'posicao' => 5,
            'is_initial' => false,
            'is_terminal' => false,
            'is_system' => false,
        ]);

        KanbanPipelineMapping::create([
            'tenant_id' => $this->tenantB->id,
            'event_kind' => 'slot_held',
            'funil_coluna_id' => $newColB->id,
            'is_active' => true,
        ]);

        // Apply transition for both tenants
        app()->instance('tenant', $this->tenantA);
        $pacienteA = Paciente::make([
            'nome' => 'Paciente A2',
            'status' => 'lead',
            'telefone_primario' => '+5511933333333',
            'funil_coluna_atual_id' => $this->colA1->id,
        ])->forceFill(['tenant_id' => $this->tenantA->id]);
        $pacienteA->save();

        app()->instance('tenant', $this->tenantB);
        $pacienteB = Paciente::make([
            'nome' => 'Paciente B2',
            'status' => 'lead',
            'telefone_primario' => '+5511944444444',
            'funil_coluna_atual_id' => $this->colB1->id,
        ])->forceFill(['tenant_id' => $this->tenantB->id]);
        $pacienteB->save();

        // Apply transitions
        app()->instance('tenant', $this->tenantA);
        $serviceA = app(KanbanAutoTransitionService::class);
        $serviceA->apply($pacienteA, 'slot_held');

        app()->instance('tenant', $this->tenantB);
        $serviceB = app(KanbanAutoTransitionService::class);
        $serviceB->apply($pacienteB, 'slot_held');

        $pacienteA->refresh();
        $pacienteB->refresh();

        // Verify: A still uses colA2
        $this->assertEquals($this->colA2->id, $pacienteA->funil_coluna_atual_id);

        // Verify: B uses new mapping
        $this->assertEquals($newColB->id, $pacienteB->funil_coluna_atual_id);
    }
}

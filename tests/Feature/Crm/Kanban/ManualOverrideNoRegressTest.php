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
 * T119 — Manual override has precedence over automatic transitions (FR-020, US3).
 *
 * Verifica que quando um paciente é movido manualmente para uma coluna
 * mais "avançada", uma transição automática que viria depois é suprimida.
 *
 * Exemplo: paciente está em "new" (posicao=1), operador o move para
 * "confirmado" (posicao=5) manualmente. Se um evento 'slot_held'
 * (target='agendado', posicao=4) chegar depois, a transição é suprimida
 * porque a coluna atual (posicao=5) >= target (posicao=4).
 */
final class ManualOverrideNoRegressTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FunilColuna $newColumn;

    private FunilColuna $scheduledColumn;

    private FunilColuna $confirmedColumn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        // Criar colunas com posições crescentes
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

        $this->scheduledColumn = FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Agendado',
            'slug' => 'agendado',
            'posicao' => 4,
            'is_initial' => false,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        $this->confirmedColumn = FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Confirmado',
            'slug' => 'confirmado',
            'posicao' => 5,
            'is_initial' => false,
            'is_terminal' => true,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        // Criar mapping para slot_held
        KanbanPipelineMapping::create([
            'tenant_id' => $this->tenant->id,
            'event_kind' => 'slot_held',
            'funil_coluna_id' => $this->scheduledColumn->id,
            'is_active' => true,
        ]);
    }

    /**
     * @test
     */
    public function test_automatic_transition_is_suppressed_when_manually_advanced(): void
    {
        // Criar paciente
        $paciente = Paciente::make([
            'nome' => 'Eduardo Franco',
            'status' => 'lead',
            'telefone_primario' => '+5511933333333',
            'funil_coluna_atual_id' => $this->newColumn->id,
        ])->forceFill(['tenant_id' => $this->tenant->id]);
        $paciente->save();

        // Operador move manualmente para "confirmado"
        $paciente->update(['funil_coluna_atual_id' => $this->confirmedColumn->id]);

        // Tentar aplicar transição automática para 'slot_held' (target: agendado, posicao=4)
        $service = app(KanbanAutoTransitionService::class);
        $event = $service->apply($paciente, 'slot_held');

        // Refresh
        $paciente->refresh();

        // Verify: transição foi suprimida (applied=false)
        $this->assertFalse($event->applied);
        $this->assertEquals('manual_override', $event->suppression_reason);

        // Verify: paciente continua em confirmado (não regrediu para agendado)
        $this->assertEquals($this->confirmedColumn->id, $paciente->funil_coluna_atual_id);

        // Verify: from_coluna_id e to_coluna_id foram registrados na auditoria
        $this->assertEquals($this->confirmedColumn->id, $event->from_coluna_id);
        $this->assertEquals($this->scheduledColumn->id, $event->to_coluna_id);
    }

    /**
     * @test
     */
    public function test_automatic_transition_applies_when_advancing(): void
    {
        // Criar paciente em agendado
        $paciente = Paciente::make([
            'nome' => 'Fernanda Gomes',
            'status' => 'lead',
            'telefone_primario' => '+5511922222222',
            'funil_coluna_atual_id' => $this->scheduledColumn->id,
        ])->forceFill(['tenant_id' => $this->tenant->id]);
        $paciente->save();

        // Criar mapping para confirmação
        KanbanPipelineMapping::create([
            'tenant_id' => $this->tenant->id,
            'event_kind' => 'reservation_confirmed',
            'funil_coluna_id' => $this->confirmedColumn->id,
            'is_active' => true,
        ]);

        // Aplicar transição para confirmado (avança de posicao=4 para posicao=5)
        $service = app(KanbanAutoTransitionService::class);
        $event = $service->apply($paciente, 'reservation_confirmed');

        // Refresh
        $paciente->refresh();

        // Verify: transição foi aplicada (applied=true)
        $this->assertTrue($event->applied);

        // Verify: paciente foi movido para confirmado
        $this->assertEquals($this->confirmedColumn->id, $paciente->funil_coluna_atual_id);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Kanban;

use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Domain\Crm\Kanban\Models\KanbanPipelineMapping;
use App\Events\Agenda\ConsultaConfirmada;
use App\Models\Agenda\Appointment;
use App\Models\Agenda\AppointmentType;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T116 — Auto-transição on appointment confirmed (FR-018, US3).
 *
 * Verifica que ao disparar ConsultaConfirmada event, o listener
 * PromoteToConfirmedOnReservationPaid move o card para a coluna
 * mapping de 'reservation_confirmed'.
 */
final class AutoTransitionConfirmedTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FunilColuna $scheduledColumn;

    private FunilColuna $confirmedColumn;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        // Criar colunas
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

        // Criar mappings
        KanbanPipelineMapping::create([
            'tenant_id' => $this->tenant->id,
            'event_kind' => 'reservation_confirmed',
            'funil_coluna_id' => $this->confirmedColumn->id,
            'is_active' => true,
        ]);
    }

    /**
     * @test
     */
    public function test_consulta_confirmada_event_promotes_to_confirmed(): void
    {
        // Criar paciente em 'agendado'
        $paciente = Paciente::make([
            'nome' => 'Ana Costa',
            'status' => 'lead',
            'telefone_primario' => '+5511977777777',
            'funil_coluna_atual_id' => $this->scheduledColumn->id,
        ])->forceFill(['tenant_id' => $this->tenant->id]);
        $paciente->save();

        // Criar professional e appointment type
        $professional = Professional::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $appointmentType = AppointmentType::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        // Criar appointment com status válido (não 'confirmado' — esse é o estado final)
        $appointment = Appointment::factory()->create([
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $paciente->id,
            'professional_id' => $professional->id,
            'appointment_type_id' => $appointmentType->id,
            'status' => 'scheduled',
        ]);

        // Disparar o evento que representa a confirmação
        event(new ConsultaConfirmada($appointment, '24h'));

        // Refresh
        $paciente->refresh();

        // Verify: paciente deve estar em confirmed column
        $this->assertEquals($this->confirmedColumn->id, $paciente->funil_coluna_atual_id);

        // Verify: KanbanCurationEvent foi criado
        $event = KanbanCurationEvent::where('tenant_id', $this->tenant->id)
            ->where('paciente_id', $paciente->id)
            ->where('event_kind', 'reservation_confirmed')
            ->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->applied);
        $this->assertEquals($this->scheduledColumn->id, $event->from_coluna_id);
        $this->assertEquals($this->confirmedColumn->id, $event->to_coluna_id);
    }
}

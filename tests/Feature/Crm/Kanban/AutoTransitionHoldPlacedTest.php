<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Kanban;

use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Domain\Crm\Kanban\Models\KanbanPipelineMapping;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Agenda\AppointmentType;
use App\Models\Agenda\SlotReservation;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T115 — Auto-transição on hold placed (FR-018, US3).
 *
 * Verifica que ao criar uma SlotReservation com holder_type='ia',
 * o listener PromoteToScheduledOnHoldPlaced dispara e move o card para
 * a coluna mapping de 'slot_held'.
 */
final class AutoTransitionHoldPlacedTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FunilColuna $initialColumn;

    private FunilColuna $scheduledColumn;

    private Channel $channel;

    private Professional $professional;

    private AppointmentType $appointmentType;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        // Criar colunas
        FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Novos',
            'slug' => 'new',
            'posicao' => 1,
            'is_initial' => true,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        $this->initialColumn = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'new')->firstOrFail();

        FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Agendado',
            'slug' => 'agendado',
            'posicao' => 4,
            'is_initial' => false,
            'is_terminal' => false,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        $this->scheduledColumn = FunilColuna::where('tenant_id', $this->tenant->id)->where('slug', 'agendado')->firstOrFail();

        // Criar mapping para 'slot_held'
        KanbanPipelineMapping::create([
            'tenant_id' => $this->tenant->id,
            'event_kind' => 'slot_held',
            'funil_coluna_id' => $this->scheduledColumn->id,
            'is_active' => true,
        ]);

        // Criar channel
        $this->channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
        ]);

        // Criar professional e appointment type
        $this->professional = Professional::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->appointmentType = AppointmentType::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);
    }

    /**
     * @test
     */
    public function test_slot_reservation_with_ia_holder_triggers_promote_to_scheduled(): void
    {
        // Criar lead
        $paciente = Paciente::make([
            'nome' => 'João Silva',
            'status' => 'lead',
            'telefone_primario' => '+5511999999999',
            'funil_coluna_atual_id' => $this->initialColumn->id,
        ])->forceFill(['tenant_id' => $this->tenant->id]);
        $paciente->save();

        // Criar conversation para o lead
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'patient_id' => $paciente->id,
            'external_thread_id' => $paciente->telefone_primario,
        ]);

        // Criar SlotReservation com holder_type='ia'
        $reservation = new SlotReservation;
        $reservation->forceFill([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->appointmentType->id,
            'holder_type' => 'ia',
            'holder_id' => (string) $conversation->id,
            'starts_at' => now()->addDays(2),
            'expires_at' => now()->addMinutes(15),
        ])->save();

        // Refresh paciente para pegar atualizações
        $paciente->refresh();

        // Verify: paciente deve estar em scheduled column
        $this->assertEquals($this->scheduledColumn->id, $paciente->funil_coluna_atual_id);

        // Verify: KanbanCurationEvent foi criado
        $event = KanbanCurationEvent::where('tenant_id', $this->tenant->id)
            ->where('paciente_id', $paciente->id)
            ->where('event_kind', 'slot_held')
            ->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->applied);
        $this->assertEquals($this->initialColumn->id, $event->from_coluna_id);
        $this->assertEquals($this->scheduledColumn->id, $event->to_coluna_id);
    }

    /**
     * @test
     */
    public function test_slot_reservation_with_user_holder_does_not_trigger_transition(): void
    {
        // Criar paciente regular (não-lead)
        $paciente = Paciente::make([
            'nome' => 'Maria Santos',
            'status' => 'ativo',
            'telefone_primario' => '+5511988888888',
            'funil_coluna_atual_id' => null,
        ])->forceFill(['tenant_id' => $this->tenant->id]);
        $paciente->save();

        // Criar SlotReservation com holder_type!='ia'
        $reservation = new SlotReservation;
        $reservation->forceFill([
            'tenant_id' => $this->tenant->id,
            'professional_id' => $this->professional->id,
            'appointment_type_id' => $this->appointmentType->id,
            'holder_type' => 'user',
            'holder_id' => '1',
            'starts_at' => now()->addDays(3),
            'expires_at' => now()->addMinutes(15),
        ])->save();

        // Refresh
        $paciente->refresh();

        // Verify: paciente NÃO deve ter funil_coluna_atual_id (continua null)
        $this->assertNull($paciente->funil_coluna_atual_id);

        // Verify: nenhum KanbanCurationEvent foi criado
        $eventCount = KanbanCurationEvent::where('tenant_id', $this->tenant->id)
            ->where('paciente_id', $paciente->id)
            ->count();
        $this->assertEquals(0, $eventCount);
    }
}

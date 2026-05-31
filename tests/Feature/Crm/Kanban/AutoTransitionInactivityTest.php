<?php

declare(strict_types=1);

namespace Tests\Feature\Crm\Kanban;

use App\Domain\Crm\Kanban\Models\KanbanCurationEvent;
use App\Domain\Crm\Kanban\Models\KanbanPipelineMapping;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Listeners\Crm\Kanban\DowngradeToLostOnInactivityListener;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * T118 — Auto-transição on inactivity (FR-018, US3).
 *
 * Verifica que ao invocar DowngradeToLostOnInactivityListener::runForTenant
 * com dias > 30, pacientes leads com last_inbound_message_at há mais de N dias
 * são movidos para a coluna mapping 'inactivity'.
 */
final class AutoTransitionInactivityTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private FunilColuna $newColumn;

    private FunilColuna $lostColumn;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        app()->instance('tenant', $this->tenant);

        // Criar colunas
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

        $this->lostColumn = FunilColuna::create([
            'tenant_id' => $this->tenant->id,
            'nome' => 'Perdido',
            'slug' => 'perdido',
            'posicao' => 10,
            'is_initial' => false,
            'is_terminal' => true,
            'motivo_obrigatorio' => false,
            'is_system' => true,
        ]);

        // Criar mapping para inatividade
        KanbanPipelineMapping::create([
            'tenant_id' => $this->tenant->id,
            'event_kind' => 'inactivity',
            'funil_coluna_id' => $this->lostColumn->id,
            'is_active' => true,
        ]);

        // Criar channel
        $this->channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
        ]);
    }

    /**
     * @test
     */
    public function test_inactive_lead_is_moved_to_lost(): void
    {
        // Criar lead com conversação antiga
        $paciente = Paciente::make([
            'nome' => 'Carlos Mendes',
            'status' => 'lead',
            'telefone_primario' => '+5511955555555',
            'funil_coluna_atual_id' => $this->newColumn->id,
        ])->forceFill(['tenant_id' => $this->tenant->id]);
        $paciente->save();

        // Criar conversation com last_inbound_message_at > 30 dias atrás
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'patient_id' => $paciente->id,
            'external_thread_id' => $paciente->telefone_primario,
            'last_inbound_message_at' => now()->subDays(35),
        ]);

        // Invocar listener manualmente
        $listener = app(DowngradeToLostOnInactivityListener::class);
        $listener->runForTenant($this->tenant, 30);

        // Refresh
        $paciente->refresh();

        // Verify: paciente deve estar em lost column
        $this->assertEquals($this->lostColumn->id, $paciente->funil_coluna_atual_id);

        // Verify: KanbanCurationEvent foi criado
        $event = KanbanCurationEvent::where('tenant_id', $this->tenant->id)
            ->where('paciente_id', $paciente->id)
            ->where('event_kind', 'inactivity')
            ->first();
        $this->assertNotNull($event);
        $this->assertTrue($event->applied);
    }

    /**
     * @test
     */
    public function test_active_lead_is_not_moved(): void
    {
        // Criar lead com mensagem recente
        $paciente = Paciente::make([
            'nome' => 'Diana Rossi',
            'status' => 'lead',
            'telefone_primario' => '+5511944444444',
            'funil_coluna_atual_id' => $this->newColumn->id,
        ])->forceFill(['tenant_id' => $this->tenant->id]);
        $paciente->save();

        // Criar conversation com last_inbound_message_at recente
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->channel->id,
            'patient_id' => $paciente->id,
            'external_thread_id' => $paciente->telefone_primario,
            'last_inbound_message_at' => now()->subDays(15),
        ]);

        // Invocar listener
        $listener = app(DowngradeToLostOnInactivityListener::class);
        $listener->runForTenant($this->tenant, 30);

        // Refresh
        $paciente->refresh();

        // Verify: paciente deve continuar em new column
        $this->assertEquals($this->newColumn->id, $paciente->funil_coluna_atual_id);

        // Verify: nenhum evento de inatividade foi criado
        $eventCount = KanbanCurationEvent::where('tenant_id', $this->tenant->id)
            ->where('paciente_id', $paciente->id)
            ->where('event_kind', 'inactivity')
            ->count();
        $this->assertEquals(0, $eventCount);
    }
}

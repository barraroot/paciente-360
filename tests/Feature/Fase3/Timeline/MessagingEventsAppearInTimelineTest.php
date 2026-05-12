<?php

namespace Tests\Feature\Fase3\Timeline;

use App\Domain\Messaging\Assignment\Events\ConversaTransferida;
use App\Domain\Messaging\Channel\Events\CanalConectado;
use App\Domain\Messaging\Channel\Events\CanalDesconectado;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Events\ConversaAssumidaPorHumano;
use App\Domain\Messaging\Conversation\Events\ConversaCriada;
use App\Domain\Messaging\Conversation\Events\ConversaReaberta;
use App\Domain\Messaging\Conversation\Events\ConversaResolvida;
use App\Domain\Messaging\Conversation\Events\ConversaRetomadaPelaIA;
use App\Domain\Messaging\Conversation\Events\ConversaVinculadaAPaciente;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Events\MensagemEnviada;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\EventoTimeline;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T266 — Testes de integração: eventos de Mensageria projetados em `eventos_timeline`.
 *
 * Valida que o wildcard listener `RegistraEventoTimelineListener` (Fase 2)
 * captura automaticamente eventos de conversa/mensagem quando `relatedPacienteId()` != null.
 *
 * Eventos de canal (CanalConectado/Desconectado) retornam null em relatedPacienteId
 * e NÃO devem aparecer na timeline — apenas em audit_logs.
 */
class MessagingEventsAppearInTimelineTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendant;

    private Paciente $paciente;

    private Channel $channel;

    private Conversation $conversation;

    private Message $message;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->attendant] = $this->tenantAndUserForRole('clinica-timeline', 'atendente');

        $this->paciente = Paciente::factory()->create([
            'tenant_id' => $this->tenant->id,
        ]);

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $this->conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->create([
                'channel_id' => $this->channel->id,
                'patient_id' => $this->paciente->id,
                'status' => 'aberta',
            ]);

        $this->message = Message::factory()
            ->for($this->conversation)
            ->create([
                'tenant_id' => $this->tenant->id,
                'direction' => 'in',
                'sender_type' => 'patient',
            ]);
    }

    #[Test]
    public function mensagem_recebida_with_patient_id_appears_in_eventos_timeline(): void
    {
        event(new MensagemRecebida($this->message, $this->conversation));

        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $this->paciente->id,
            'tipo' => 'mensagem.recebida',
        ]);
    }

    #[Test]
    public function mensagem_enviada_with_patient_appears_in_timeline(): void
    {
        $outboundMsg = Message::factory()
            ->for($this->conversation)
            ->create([
                'tenant_id' => $this->tenant->id,
                'direction' => 'out',
                'sender_type' => 'user',
            ]);

        event(new MensagemEnviada($outboundMsg, $this->conversation));

        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $this->paciente->id,
            'tipo' => 'mensagem.enviada',
        ]);
    }

    #[Test]
    public function conversa_criada_appears_in_timeline(): void
    {
        event(new ConversaCriada($this->conversation));

        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $this->paciente->id,
            'tipo' => 'conversa.criada',
        ]);
    }

    #[Test]
    public function conversa_resolvida_appears_in_timeline(): void
    {
        event(new ConversaResolvida($this->conversation, 'manual', $this->attendant->id));

        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $this->paciente->id,
            'tipo' => 'conversa.resolvida',
        ]);
    }

    #[Test]
    public function conversa_reaberta_appears_in_timeline(): void
    {
        event(new ConversaReaberta($this->conversation, 'nova_msg'));

        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $this->paciente->id,
            'tipo' => 'conversa.reaberta',
        ]);
    }

    #[Test]
    public function conversa_transferida_appears_in_timeline(): void
    {
        event(new ConversaTransferida(
            conversation: $this->conversation,
            fromUserId: $this->attendant->id,
            toUserId: null,
            toRole: 'medico',
            noteSanitized: 'transferência para médico',
            executorId: $this->attendant->id,
        ));

        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $this->paciente->id,
            'tipo' => 'conversa.transferida',
        ]);
    }

    #[Test]
    public function conversa_assumida_por_humano_appears_in_timeline(): void
    {
        event(new ConversaAssumidaPorHumano(
            conversation: $this->conversation,
            executorId: $this->attendant->id,
            reason: 'manual_click',
            pauseDurationSeconds: 1800,
        ));

        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $this->paciente->id,
            'tipo' => 'conversa.assumida_por_humano',
        ]);
    }

    #[Test]
    public function conversa_retomada_pela_ia_appears_in_timeline(): void
    {
        event(new ConversaRetomadaPelaIA(
            conversation: $this->conversation,
            mode: 'timeout',
        ));

        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $this->paciente->id,
            'tipo' => 'conversa.retomada_pela_ia',
        ]);
    }

    #[Test]
    public function canal_events_without_patient_id_no_t_projected_to_timeline(): void
    {
        $timelineBefore = EventoTimeline::count();

        // Canal events não têm relatedPacienteId
        event(new CanalConectado($this->channel, $this->attendant->id));

        // Não deve criar entrada na timeline
        $this->assertEquals($timelineBefore, EventoTimeline::count());
    }

    #[Test]
    public function canal_desconectado_not_in_timeline(): void
    {
        $timelineBefore = EventoTimeline::count();

        event(new CanalDesconectado($this->channel, 'manual', $this->attendant->id));

        $this->assertEquals($timelineBefore, EventoTimeline::count());
    }

    #[Test]
    public function conversa_vinculada_a_paciente_appears_in_timeline(): void
    {
        event(new ConversaVinculadaAPaciente(
            conversation: $this->conversation,
            pacienteId: $this->paciente->id,
            modo: 'manual',
        ));

        $this->assertDatabaseHas('eventos_timeline', [
            'tenant_id' => $this->tenant->id,
            'paciente_id' => $this->paciente->id,
            'tipo' => 'conversa.vinculada_paciente',
        ]);
    }
}

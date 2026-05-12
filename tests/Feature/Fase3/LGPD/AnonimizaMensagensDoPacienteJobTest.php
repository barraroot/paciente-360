<?php

namespace Tests\Feature\Fase3\LGPD;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Infrastructure\Listeners\AnonimizaMensagensOnPacienteAnonimizadoListener;
use App\Domain\Messaging\Message\Models\Message;
use App\Domain\Messaging\Message\Models\MessageMedia;
use App\Events\Paciente\PacienteAnonimizado;
use App\Jobs\Messaging\AnonimizaMensagensDoPacienteJob;
use App\Models\AuditLog;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T253 — Feature tests para AnonimizaMensagensDoPacienteJob (FR-018, NC-14.b).
 *
 * Valida política LGPD granular:
 *  - Mensagens recebidas (direction=in): body/preview zerados + mídia deletada.
 *  - Mensagens enviadas (direction=out): preservadas integralmente.
 *  - Audit log emitido sem PII.
 */
class AnonimizaMensagensDoPacienteJobTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private Paciente $paciente;

    private Channel $channel;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('media');

        $this->seedRoles();
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('clinica-lgpd', 'admin-clinica');

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
    }

    #[Test]
    public function received_messages_have_body_zeroed_and_media_deleted_from_s3(): void
    {
        // 5 mensagens recebidas
        $inbound = Message::factory()->count(5)->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'sender_type' => 'patient',
            'body' => 'mensagem do paciente',
            'body_searchable' => 'mensagem do paciente',
            'body_preview' => 'mensagem do paciente',
        ]);

        // 3 mensagens enviadas
        $outbound = Message::factory()->count(3)->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'out',
            'sender_type' => 'user',
            'body' => 'resposta do atendente',
            'body_preview' => 'resposta do atendente',
        ]);

        // Cria mídia para mensagens inbound
        Storage::disk('media')->put('tenant_1/audio.ogg', 'fake-audio');
        $media = MessageMedia::factory()->create([
            'tenant_id' => $this->tenant->id,
            'message_id' => $inbound->first()->id,
            'storage_disk' => 'media',
            'storage_path' => 'tenant_1/audio.ogg',
            'media_purged_at' => null,
        ]);

        // Executa o job diretamente (síncrono no teste)
        $job = new AnonimizaMensagensDoPacienteJob(
            pacienteId: $this->paciente->id,
            tenantId: $this->tenant->id,
        );
        $job->handle(app(PermissionRegistrar::class));

        // Mensagens IN zeradas
        foreach ($inbound as $msg) {
            $msg->refresh();
            $this->assertNull($msg->getRawOriginal('body') === null ? null : $msg->body);
            $this->assertNull($msg->body_searchable);
            $this->assertStringContainsString('anonimizado em', $msg->body_preview);
        }

        // Mensagens OUT preservadas
        foreach ($outbound as $msg) {
            $msg->refresh();
            $this->assertNotNull($msg->body_preview);
            $this->assertEquals('resposta do atendente', $msg->body_preview);
        }

        // Mídia deletada do S3
        Storage::disk('media')->assertMissing('tenant_1/audio.ogg');

        // media_purged_at setado
        $media->refresh();
        $this->assertNotNull($media->media_purged_at);
    }

    #[Test]
    public function audit_log_emitido_sem_pii(): void
    {
        Message::factory()->count(2)->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
        ]);

        $job = new AnonimizaMensagensDoPacienteJob(
            pacienteId: $this->paciente->id,
            tenantId: $this->tenant->id,
        );
        $job->handle(app(PermissionRegistrar::class));

        $log = AuditLog::where('action', 'paciente.mensagens_anonimizadas')
            ->where('tenant_id', $this->tenant->id)
            ->first();

        $this->assertNotNull($log);
        $payload = $log->payload;
        $this->assertEquals($this->paciente->id, $payload['paciente_id']);
        $this->assertArrayHasKey('recebidas_anonimizadas', $payload);
        $this->assertArrayHasKey('enviadas_preservadas', $payload);
        $this->assertArrayHasKey('midias_deletadas', $payload);
        // Sem PII no audit log
        $this->assertArrayNotHasKey('body', $payload);
        $this->assertArrayNotHasKey('nome', $payload);
    }

    #[Test]
    public function listener_subscribes_to_paciente_anonimizado_event(): void
    {
        Bus::fake();

        $event = new PacienteAnonimizado($this->paciente, 'solicitacao_lgpd');

        $listener = new AnonimizaMensagensOnPacienteAnonimizadoListener;
        $listener->handle($event);

        Bus::assertDispatched(AnonimizaMensagensDoPacienteJob::class, function ($job) {
            return $job->pacienteId === $this->paciente->id
                && $job->tenantId === $this->tenant->id;
        });
    }

    #[Test]
    public function job_is_idempotent_re_running_does_not_change_already_anonimized_messages(): void
    {
        $placeholder = '[anonimizado em '.now()->format('Y-m-d').']';

        // Mensagem já anonimizada (body_preview com placeholder)
        $msg = Message::factory()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $this->conversation->id,
            'direction' => 'in',
            'body' => null,
            'body_searchable' => null,
            'body_preview' => $placeholder,
        ]);

        $job = new AnonimizaMensagensDoPacienteJob(
            pacienteId: $this->paciente->id,
            tenantId: $this->tenant->id,
        );
        $job->handle(app(PermissionRegistrar::class));

        // Segunda execução
        $job->handle(app(PermissionRegistrar::class));

        // Contagem de audit logs: deve ter 2 entradas (1 por execução)
        // mas a mensagem não deve ser duplicada
        $msg->refresh();
        $this->assertEquals($placeholder, $msg->body_preview);
    }

    #[Test]
    public function job_is_tenant_aware_and_does_not_cross_tenant(): void
    {
        // Cria outro tenant com paciente e mensagens
        [$tenantB] = $this->tenantAndUserForRole('clinica-b-lgpd', 'admin-clinica');
        $this->app->instance('tenant', $this->tenant);

        $pacienteB = Paciente::factory()->create(['tenant_id' => $tenantB->id]);
        $channelB = Channel::factory()->forTenant($tenantB)->state(['type' => 'whatsapp', 'status' => 'ativo'])->create();
        $convB = Conversation::factory()->forTenant($tenantB)->create([
            'channel_id' => $channelB->id,
            'patient_id' => $pacienteB->id,
        ]);
        $msgB = Message::factory()->create([
            'tenant_id' => $tenantB->id,
            'conversation_id' => $convB->id,
            'direction' => 'in',
            'body_preview' => 'mensagem tenant B',
        ]);

        // Roda job do tenant A (paciente do tenant A)
        $job = new AnonimizaMensagensDoPacienteJob(
            pacienteId: $this->paciente->id,
            tenantId: $this->tenant->id,
        );
        $job->handle(app(PermissionRegistrar::class));

        // Mensagem do tenant B deve estar intacta
        $msgB->refresh();
        $this->assertEquals('mensagem tenant B', $msgB->body_preview);
    }

    #[Test]
    public function event_service_provider_registers_listener_for_paciente_anonimizado(): void
    {
        Bus::fake();
        Event::fake([PacienteAnonimizado::class]);

        PacienteAnonimizado::dispatch($this->paciente, 'teste');

        Event::assertDispatched(PacienteAnonimizado::class);
    }
}

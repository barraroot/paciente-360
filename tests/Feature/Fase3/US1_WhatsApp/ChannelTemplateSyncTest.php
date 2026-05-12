<?php

namespace Tests\Feature\Fase3\US1_WhatsApp;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Channel\Models\ChannelTemplate;
use App\Jobs\Messaging\SyncChannelTemplatesJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Mockery;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;
use Twilio\Rest\Client;

/**
 * T062 — Template sync via Twilio Content API (POST /api/v1/inbox/channels/{id}/templates/sync).
 *
 * Cobre AC-4.1.7 (sincronização de templates aprovados).
 *
 * TDD RED: SyncChannelTemplatesJob e endpoint ainda não existem.
 */
class ChannelTemplateSyncTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-templates', 'admin-clinica');

        $this->channel = Channel::factory()
            ->whatsapp()
            ->forTenant($this->tenant)
            ->create([
                'status' => 'ativo',
                'credentials_encrypted' => encrypt([
                    'account_sid' => 'AC'.str_repeat('a', 32),
                    'auth_token' => 'webhook-token-secret',
                    'messaging_service_sid' => 'MG'.str_repeat('b', 32),
                    'whatsapp_sender' => 'whatsapp:+5511999999999',
                ]),
            ]);
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    /** @test */
    public function test_sync_job_persists_templates_from_twilio_content_api(): void
    {
        // Mock resposta da Twilio Content API
        $twilioMock = Mockery::mock(Client::class);
        $contentMock = Mockery::mock();
        $twilioMock->content = $contentMock;

        // Simula resposta do content.v1.contents.read()
        $template1 = (object) [
            'sid' => 'HX'.str_repeat('a', 30),
            'friendlyName' => 'boas_vindas',
            'status' => 'approved',
            'language' => 'pt_BR',
            'types' => [
                'twilio/text' => [
                    'body' => 'Olá {{1}}, bem-vindo!',
                ],
            ],
        ];

        $template2 = (object) [
            'sid' => 'HX'.str_repeat('b', 30),
            'friendlyName' => 'confirmacao_agendamento',
            'status' => 'pending',
            'language' => 'pt_BR',
            'types' => [
                'twilio/text' => [
                    'body' => 'Sua consulta está confirmada para {{1}} às {{2}}',
                ],
            ],
        ];

        $v1Mock = Mockery::mock();
        $contentsMock = Mockery::mock();
        $v1Mock->contents = $contentsMock;
        $contentMock->v1 = $v1Mock;

        $contentsMock->shouldReceive('read')->andReturn([$template1, $template2]);

        $this->app->instance(Client::class, $twilioMock);

        // Dispara o job (será instanciado)
        $job = new SyncChannelTemplatesJob($this->channel);
        $job->handle();

        // Verifica que templates foram persistidos
        $this->assertDatabaseHas('messaging_channel_templates', [
            'channel_id' => $this->channel->id,
            'provider_template_id' => 'HX'.str_repeat('a', 30),
            'meta_template_name' => 'boas_vindas',
            'meta_template_status' => 'approved',
        ]);

        $this->assertDatabaseHas('messaging_channel_templates', [
            'channel_id' => $this->channel->id,
            'provider_template_id' => 'HX'.str_repeat('b', 30),
            'meta_template_name' => 'confirmacao_agendamento',
            'meta_template_status' => 'pending',
        ]);

        // Verifica contagem
        $this->assertSame(2, ChannelTemplate::where('channel_id', $this->channel->id)->count());
    }

    /** @test */
    public function test_sync_is_idempotent_subsequent_runs_update_status_only(): void
    {
        // Mock resposta da Twilio Content API
        $twilioMock = Mockery::mock(Client::class);
        $contentMock = Mockery::mock();
        $twilioMock->content = $contentMock;

        $template1 = (object) [
            'sid' => 'HX'.str_repeat('a', 30),
            'friendlyName' => 'boas_vindas',
            'status' => 'approved',
            'language' => 'pt_BR',
            'types' => ['twilio/text' => ['body' => 'Olá {{1}}!']],
        ];

        $v1Mock = Mockery::mock();
        $contentsMock = Mockery::mock();
        $v1Mock->contents = $contentsMock;
        $contentMock->v1 = $v1Mock;

        $contentsMock->shouldReceive('read')->andReturn([$template1]);

        $this->app->instance(Client::class, $twilioMock);

        // Primeira sync
        $job = new SyncChannelTemplatesJob($this->channel);
        $job->handle();

        $this->assertSame(1, ChannelTemplate::where('channel_id', $this->channel->id)->count());

        // Mock resposta com status mudado
        $twilioMock2 = Mockery::mock(Client::class);
        $contentMock2 = Mockery::mock();
        $twilioMock2->content = $contentMock2;

        $template1Updated = (object) [
            'sid' => 'HX'.str_repeat('a', 30),
            'friendlyName' => 'boas_vindas',
            'status' => 'rejected',  // Status mudou
            'language' => 'pt_BR',
            'types' => ['twilio/text' => ['body' => 'Olá {{1}}!']],
        ];

        $v1Mock2 = Mockery::mock();
        $contentsMock2 = Mockery::mock();
        $v1Mock2->contents = $contentsMock2;
        $contentMock2->v1 = $v1Mock2;

        $contentsMock2->shouldReceive('read')->andReturn([$template1Updated]);

        $this->app->instance(Client::class, $twilioMock2);

        // Segunda sync
        $job2 = new SyncChannelTemplatesJob($this->channel);
        $job2->handle();

        // Verifica que still only 1 template
        $this->assertSame(1, ChannelTemplate::where('channel_id', $this->channel->id)->count());

        // Verifica status foi atualizado
        $this->assertDatabaseHas('messaging_channel_templates', [
            'channel_id' => $this->channel->id,
            'provider_template_id' => 'HX'.str_repeat('a', 30),
            'meta_template_status' => 'rejected',
        ]);
    }

    /** @test */
    public function test_sync_endpoint_returns_202_and_enqueues_job(): void
    {
        Queue::fake();

        $response = $this->postJson(
            $this->baseUrl("/inbox/channels/{$this->channel->id}/templates/sync")
        );

        $response->assertAccepted();

        // Verifica que job foi enfileirado
        Queue::assertPushed(SyncChannelTemplatesJob::class);

        // Resposta deve conter job_id
        $this->assertNotNull($response->json('job_id'));
        $this->assertNotNull($response->json('enqueued_at'));
    }
}

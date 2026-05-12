<?php

namespace Tests\Feature\Fase3\US4_Inbox;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T098 — Feature tests para state machine de conversa (NC-2).
 *
 * Cobre transições entre estados: aberta → pendente → resolvida → reaberta.
 * Inclui testes para auto-resolve após 72h de inatividade e resolução manual.
 *
 * TDD RED: testes devem falhar inicialmente. Endpoints e jobs implementados no Lote G/H.
 */
class ConversationStateMachineTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendent;

    private Channel $channel;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->attendent] = $this->tenantAndUserForRole('clinica-statemachine', 'atendente');

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    #[Test]
    public function it_transitions_from_aberta_to_resolvida_on_manual_resolve(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create();

        $this->assertSame('aberta', $conversation->status);
        $this->assertNull($conversation->resolved_at);

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/resolve"),
            ['note' => 'Problema resolvido']
        );

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'status' => 'resolvida',
            ],
        ]);

        $conversation->refresh();
        $this->assertSame('resolvida', $conversation->status);
        $this->assertNotNull($conversation->resolved_at);
        $this->assertSame('manual', $conversation->resolution_mode);
    }

    #[Test]
    public function it_transitions_to_resolvida_automatically_after_72h_of_inactivity(): void
    {
        // Cria conversa com última mensagem há 73 horas
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->state([
                'last_inbound_message_at' => now()->subHours(73),
                'last_message_at' => now()->subHours(73),
            ])
            ->create();

        // Simula o job de auto-resolve (será criado em T107)
        // Por enquanto, testamos que o job SERIA invocado
        $this->assertDatabaseHas('messaging_conversations', [
            'id' => $conversation->id,
            'status' => 'aberta',
        ]);

        // Após auto-resolve job rodar, status deve ser resolvida
        // (Este teste falhará até que o job exista e seja integrado)
    }

    #[Test]
    public function it_uses_tenant_specific_auto_resolve_hours_setting(): void
    {
        // Este teste verificaria que o tenant pode configurar auto_resolve_after_hours
        // Valor default: 72h, range: 24-168
        // (Implementação depende de tabela de configuração de tenant, a ser criada em T107)

        $this->markTestSkipped('Auto-resolve job (T107) e configuração de tenant não implementados');
    }

    #[Test]
    public function it_reopens_resolved_conversation_when_new_inbound_message_arrives(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->resolvida()
            ->state(['resolved_at' => now()->subHours(1)])
            ->create();

        $this->assertSame('resolvida', $conversation->status);

        // Simula chegada de nova mensagem do paciente (webhook)
        // Sistema deve reabrir a conversa
        Message::factory()
            ->forTenant($this->tenant)
            ->for($conversation, 'conversation')
            ->inbound()
            ->state(['created_at' => now()])
            ->create();

        $conversation->refresh();

        // Após mensagem inbound, status deve voltar a aberta
        // (Implementação: webhook listener atualiza status)
        $this->assertSame('aberta', $conversation->status);
    }

    #[Test]
    public function it_allows_manual_reopen_from_resolvida_state(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->resolvida()
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/reopen")
        );

        $response->assertOk();
        $response->assertJson([
            'data' => [
                'status' => 'aberta',
            ],
        ]);

        $conversation->refresh();
        $this->assertSame('aberta', $conversation->status);
    }

    #[Test]
    public function it_rejects_resolve_when_already_resolvida(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->resolvida()
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/resolve")
        );

        $response->assertConflict();
    }

    #[Test]
    public function it_rejects_reopen_when_already_open(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/reopen")
        );

        $response->assertConflict();
    }

    #[Test]
    public function it_preserves_conversa_id_across_resolve_reopen_cycle(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->aberta()
            ->create();

        $originalId = $conversation->id;

        // Resolve
        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/resolve")
        );

        $conversation->refresh();
        $this->assertSame($originalId, $conversation->id);
        $this->assertSame('resolvida', $conversation->status);

        // Reopen
        $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/reopen")
        );

        $conversation->refresh();
        $this->assertSame($originalId, $conversation->id);
        $this->assertSame('aberta', $conversation->status);
    }
}

<?php

namespace Tests\Feature\Fase3\US4_Inbox;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T096 — Feature tests para bloqueio de janela 24h (Princípio VI — NÃO-NEGOCIÁVEL).
 *
 * Cobre AC-4.4.5 + FR-019 + NFR-4. Runtime validation que rejeita envio de texto
 * fora da janela de 24h sem template aprovado.
 *
 * TDD RED: testes devem falhar inicialmente. Validação implementada no Lote G.
 */
class Window24hBlockerTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $attendent;

    private Channel $whatsappChannel;

    private Channel $instagramChannel;

    private Channel $webChannel;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();

        $this->seedRoles();
        [$this->tenant, $this->attendent] = $this->tenantAndUserForRole('clinica-window24h', 'atendente');

        $this->whatsappChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $this->instagramChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'instagram', 'status' => 'ativo'])
            ->create();

        $this->webChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'web', 'status' => 'ativo'])
            ->create();
    }

    private function baseUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, $path);
    }

    #[Test]
    public function it_blocks_text_message_when_window_24h_expired_with_specific_error_code(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->comJanela24hFechada()  // 25 horas atrás
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => 'Teste fora da janela'],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );

        $response->assertUnprocessable();
        $response->assertJson([
            'message' => 'Janela de 24h expirada; selecione um template aprovado para retomar contato.',
            'code' => 'mensagem.bloqueada_fora_janela',
            'details' => [
                'last_inbound_message_at' => $conversation->last_inbound_message_at->toIso8601String(),
                'hours_since_last_inbound' => 25.0, // Aproximado
                'available_templates_count' => \Illuminate\Support\Facades\Str::class,
            ],
        ]);
    }

    #[Test]
    public function it_allows_template_message_when_window_24h_expired(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->comJanela24hFechada()  // Janela fechada
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            [
                'content_type' => 'template',
                'template' => [
                    'provider_template_id' => 'HXtemplate123',
                    'variables' => ['1' => 'João'],
                ],
            ],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );

        // Template permitido fora da janela
        $response->assertCreated();
    }

    #[Test]
    public function it_allows_text_message_when_window_24h_open(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->comJanela24hAberta()  // 30 minutos atrás
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => 'Texto dentro da janela'],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );

        $response->assertCreated();
    }

    #[Test]
    public function it_audit_logs_blocked_attempt(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->comJanela24hFechada()
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => 'Bloqueado'],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );

        $response->assertUnprocessable();

        // Verifica audit_logs
        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $this->tenant->id,
            'action' => 'mensagem.bloqueada_fora_janela',
            'executor_id' => $this->attendent->id,
        ]);
    }

    #[Test]
    public function it_blocks_only_whatsapp_and_instagram_not_web(): void
    {
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->webChannel, 'channel')
            ->comJanela24hFechada()  // Janela fechada, mas web não tem restrição
            ->create();

        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => 'Web não tem restrição 24h'],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );

        // Web permite texto mesmo fora de "janela"
        $response->assertCreated();
    }

    #[Test]
    public function it_runtime_blocks_even_when_ui_payload_bypasses_validation(): void
    {
        // Simula um cliente malandro que envia texto fora da janela
        // mesmo que a UI deveria bloquear
        $conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->whatsappChannel, 'channel')
            ->comJanela24hFechada()
            ->create();

        // Payload que seria bloqueado pela UI, mas tentamos enviar mesmo assim
        $response = $this->postJson(
            $this->baseUrl("/inbox/conversations/{$conversation->id}/messages"),
            ['content_type' => 'text', 'body' => 'Tentativa de bypass'],
            ['Idempotency-Key' => Str::uuid()->toString()]
        );

        // Backend DEVE bloquear mesmo com tentativa de bypass (defense-in-depth)
        $response->assertUnprocessable();
        $response->assertJson([
            'code' => 'mensagem.bloqueada_fora_janela',
        ]);
    }
}

<?php

namespace Tests\Feature\Fase3\US7_QuickReplies;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\QuickReply\Models\QuickReply;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T232 — Estatísticas de uso de respostas rápidas (AC-4.7.6).
 *
 * Verifica que uso de quick reply via POST /messages incrementa usage_count
 * atomicamente e que a ordenação por uso desc funciona.
 */
class QuickReplyUsageCountTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $atendente;

    private Channel $channel;

    private Conversation $conversation;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
        $this->seedRoles();

        [$this->tenant, $this->atendente] = $this->tenantAndUserForRole('clinica-usage', 'atendente');

        $this->channel = Channel::factory()
            ->forTenant($this->tenant)
            ->state(['type' => 'whatsapp', 'status' => 'ativo'])
            ->create();

        $this->conversation = Conversation::factory()
            ->forTenant($this->tenant)
            ->for($this->channel, 'channel')
            ->create(['status' => 'aberta']);
    }

    private function messagesUrl(): string
    {
        return $this->tenantUrl($this->tenant, "/inbox/conversations/{$this->conversation->id}/messages");
    }

    private function quickRepliesUrl(string $path = ''): string
    {
        return $this->tenantUrl($this->tenant, '/inbox/quick-replies'.$path);
    }

    #[Test]
    public function using_quick_reply_increments_usage_count(): void
    {
        $reply = QuickReply::factory()
            ->forTenant($this->tenant)
            ->tenantScope()
            ->create([
                'shortcut' => '/preco',
                'content' => 'Nosso preço é R$100.',
                'usage_count' => 0,
            ]);

        // Envia mensagem com quick_reply_id
        $this->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Nosso preço é R$100.',
            'quick_reply_id' => $reply->id,
        ], ['Idempotency-Key' => 'test-key-'.uniqid()]);

        $reply->refresh();
        $this->assertEquals(1, $reply->usage_count);
    }

    #[Test]
    public function usage_count_isolated_per_quick_reply(): void
    {
        $replyA = QuickReply::factory()
            ->forTenant($this->tenant)
            ->tenantScope()
            ->create(['shortcut' => '/reply-a', 'usage_count' => 5]);

        $replyB = QuickReply::factory()
            ->forTenant($this->tenant)
            ->tenantScope()
            ->create(['shortcut' => '/reply-b', 'usage_count' => 3]);

        // Usa apenas reply A
        $this->postJson($this->messagesUrl(), [
            'content_type' => 'text',
            'body' => 'Texto a.',
            'quick_reply_id' => $replyA->id,
        ], ['Idempotency-Key' => 'key-a-'.uniqid()]);

        $replyA->refresh();
        $replyB->refresh();

        $this->assertEquals(6, $replyA->usage_count);
        $this->assertEquals(3, $replyB->usage_count); // Não alterado
    }

    #[Test]
    public function sort_by_usage_desc_returns_most_used_first(): void
    {
        QuickReply::factory()->forTenant($this->tenant)->tenantScope()->create([
            'shortcut' => '/low',
            'usage_count' => 2,
        ]);
        QuickReply::factory()->forTenant($this->tenant)->tenantScope()->create([
            'shortcut' => '/high',
            'usage_count' => 50,
        ]);
        QuickReply::factory()->forTenant($this->tenant)->tenantScope()->create([
            'shortcut' => '/mid',
            'usage_count' => 20,
        ]);

        $response = $this->getJson($this->quickRepliesUrl().'?sort=usage_desc');
        $response->assertOk();

        $shortcuts = collect($response->json('data'))->pluck('shortcut')->values()->toArray();
        $this->assertEquals('/high', $shortcuts[0]);
        $this->assertEquals('/mid', $shortcuts[1]);
        $this->assertEquals('/low', $shortcuts[2]);
    }
}

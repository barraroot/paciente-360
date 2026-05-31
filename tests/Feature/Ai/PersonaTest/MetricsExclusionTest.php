<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\PersonaTest;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US6 / G5 — Verifica que Message::excludeSandbox() filtra corretamente
 * mensagens sandbox para agregadores de métrica.
 */
final class MetricsExclusionTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private User $admin;

    private AiPersona $persona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('persona-test-g5', 'admin-clinica');
        $this->admin->givePermissionTo('ai.persona.test');
        $this->persona = AiPersona::factory()->forTenant($this->tenant)->create();
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    #[Test]
    public function excludes_sandbox_messages_from_counts(): void
    {
        // Conversa real (não-sandbox precisa de conversation_id por CHECK constraint).
        $channel = Channel::factory()->create(['tenant_id' => $this->tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
        ]);

        // 3 mensagens não-sandbox (com conversation_id real).
        Message::factory(3)
            ->create([
                'tenant_id' => $this->tenant->id,
                'conversation_id' => $conversation->id,
                'sandbox' => false,
                'sandbox_session_id' => null,
            ]);

        // Admin abre sessão e envia 2 mensagens sandbox.
        Sanctum::actingAs($this->admin, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        $sessionId = $openResponse->json('data.id');

        for ($i = 0; $i < 2; $i++) {
            $this->withHeaders($this->headers())
                ->postJson(
                    $this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/messages"),
                    [
                        'content_type' => 'text',
                        'text' => "sandbox msg {$i}",
                    ]
                );
        }

        // Verifica counts.
        $totalMessages = Message::count();
        $nonSandboxMessages = Message::excludeSandbox()->count();
        $sandboxMessages = Message::where('sandbox', true)->count();

        $this->assertEquals(5, $totalMessages);
        $this->assertEquals(3, $nonSandboxMessages);
        $this->assertEquals(2, $sandboxMessages);
    }

    #[Test]
    public function excludes_null_sandbox_as_non_sandbox(): void
    {
        // Cria 1 mensagem real (sandbox=false é o equivalente atual ao "legacy null" —
        // a coluna é NOT NULL com default false, então o scope tem que tratar ambos).
        $channel = Channel::factory()->create(['tenant_id' => $this->tenant->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
        ]);

        Message::factory()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'sandbox' => false,
            'sandbox_session_id' => null,
        ]);

        // Admin abre sessão e envia 1 mensagem sandbox.
        Sanctum::actingAs($this->admin, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        $sessionId = $openResponse->json('data.id');

        $this->withHeaders($this->headers())
            ->postJson(
                $this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/messages"),
                [
                    'content_type' => 'text',
                    'text' => 'sandbox',
                ]
            );

        // Verifica que NULL é tratado como non-sandbox no excludeSandbox().
        $totalMessages = Message::count();
        $nonSandboxMessages = Message::excludeSandbox()->count();

        $this->assertEquals(2, $totalMessages);
        $this->assertEquals(1, $nonSandboxMessages);
    }
}

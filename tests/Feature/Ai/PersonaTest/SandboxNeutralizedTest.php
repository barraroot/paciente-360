<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\PersonaTest;

use App\Domain\Ai\Persona\Models\AiPersona;
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
 * US6 / G2 — Verifica que mensagens sandbox não criam conversas reais
 * nem tocam canais de delivery (Twilio, Evolution, IG).
 */
final class SandboxNeutralizedTest extends TestCase
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
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('persona-test-g2', 'admin-clinica');
        $this->admin->givePermissionTo('ai.persona.test');
        $this->persona = AiPersona::factory()->forTenant($this->tenant)->create();
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    #[Test]
    public function sandbox_message_has_no_conversation(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        $sessionId = $openResponse->json('data.id');

        $this->withHeaders($this->headers())
            ->postJson(
                $this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/messages"),
                [
                    'content_type' => 'text',
                    'text' => 'test message',
                ]
            );

        $message = Message::where('sandbox_session_id', $sessionId)->first();
        $this->assertNotNull($message);
        $this->assertNull($message->conversation_id);
        $this->assertTrue($message->sandbox);
    }

    #[Test]
    public function sandbox_messages_do_not_create_conversations(): void
    {
        $conversationCountBefore = Conversation::count();

        Sanctum::actingAs($this->admin, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        $sessionId = $openResponse->json('data.id');

        for ($i = 0; $i < 3; $i++) {
            $this->withHeaders($this->headers())
                ->postJson(
                    $this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/messages"),
                    [
                        'content_type' => 'text',
                        'text' => "message {$i}",
                    ]
                );
        }

        $conversationCountAfter = Conversation::count();
        $this->assertEquals($conversationCountBefore, $conversationCountAfter);
    }

    #[Test]
    public function sandbox_messages_filtered_by_exclude_sandbox_scope(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        $sessionId = $openResponse->json('data.id');

        // Envia 2 sandbox messages.
        for ($i = 0; $i < 2; $i++) {
            $this->withHeaders($this->headers())
                ->postJson(
                    $this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/messages"),
                    [
                        'content_type' => 'text',
                        'text' => "msg {$i}",
                    ]
                );
        }

        $totalMessages = Message::count();
        $nonSandboxMessages = Message::excludeSandbox()->count();

        $this->assertEquals(2, $totalMessages);
        $this->assertEquals(0, $nonSandboxMessages);
    }
}

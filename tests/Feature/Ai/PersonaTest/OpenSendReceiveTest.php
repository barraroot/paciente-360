<?php

declare(strict_types=1);

namespace Tests\Feature\Ai\PersonaTest;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Message\Models\Message;
use App\Events\Ai\Persona\PersonaTestMessageBroadcasted;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Laravel\Sanctum\Sanctum;
use PHPUnit\Framework\Attributes\Test;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US6 / G1 — Abre sessão de teste, envia mensagem, verifica broadcast.
 */
final class OpenSendReceiveTest extends TestCase
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
        [$this->tenant, $this->admin] = $this->tenantAndUserForRole('persona-test-g1', 'admin-clinica');
        $this->admin->givePermissionTo('ai.persona.test');
        $this->persona = AiPersona::factory()->forTenant($this->tenant)->create();
    }

    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    #[Test]
    public function opens_test_session_with_persona_snapshot(): void
    {
        Sanctum::actingAs($this->admin, ['*']);

        $response = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"))
            ->assertCreated();

        $response->assertJsonPath('data.status', 'open');
        $response->assertJsonPath('data.persona_id', $this->persona->id);
        $this->assertNotEmpty($response->json('data.mcp_token'));
        $this->assertNotEmpty($response->json('data.echo_channel'));
        $this->assertIsArray($response->json('data.persona_snapshot'));

        $this->assertDatabaseHas('persona_test_sessions', [
            'tenant_id' => $this->tenant->id,
            'persona_id' => $this->persona->id,
            'admin_user_id' => $this->admin->id,
            'status' => 'open',
        ]);
    }

    #[Test]
    public function sends_message_to_open_session(): void
    {
        Event::fake([PersonaTestMessageBroadcasted::class]);

        Sanctum::actingAs($this->admin, ['*']);

        $openResponse = $this->withHeaders($this->headers())
            ->postJson($this->tenantUrl($this->tenant, "/ai/personas/{$this->persona->id}/test-sessions"));

        $sessionId = $openResponse->json('data.id');

        $messageResponse = $this->withHeaders($this->headers())
            ->postJson(
                $this->tenantUrl($this->tenant, "/ai/persona-test-sessions/{$sessionId}/messages"),
                [
                    'content_type' => 'text',
                    'text' => 'oi',
                ]
            )
            ->assertAccepted();

        // `body` é criptografado em repouso (cast encrypted) — checa via model.
        $message = Message::query()
            ->where('sandbox_session_id', $sessionId)
            ->where('sandbox', true)
            ->first();

        $this->assertNotNull($message);
        $this->assertSame('oi', $message->body);
        $this->assertSame($this->tenant->id, $message->tenant_id);

        Event::assertDispatched(PersonaTestMessageBroadcasted::class, 1);
    }

    #[Test]
    public function message_has_correct_direction_and_sender(): void
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
                    'text' => 'teste',
                ]
            );

        $message = Message::where('sandbox_session_id', $sessionId)->first();
        $this->assertNotNull($message);
        $this->assertEquals('in', $message->direction);
        $this->assertEquals('patient', $message->sender_type);
        $this->assertEquals('delivered', $message->status);
    }
}

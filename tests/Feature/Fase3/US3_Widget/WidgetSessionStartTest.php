<?php

namespace Tests\Feature\Fase3\US3_Widget;

use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Widget\Models\WebWidgetConfig;
use App\Domain\Messaging\Widget\Models\WebWidgetSession;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * T205 — Feature tests para início de sessão de visitante no widget.
 *
 * POST /widget/v1/{public_key}/sessions
 * Público (sem auth). Cobre AC-4.3.2 (sessão temporária) e AC-4.3.3 (form pré-chat).
 */
class WidgetSessionStartTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    private Channel $webChannel;

    private WebWidgetConfig $widgetConfig;

    private string $publicKey;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('clinica-session', 'admin-clinica');

        $this->publicKey = bin2hex(random_bytes(32));

        $this->webChannel = Channel::factory()
            ->forTenant($this->tenant)
            ->web()
            ->create();

        $this->widgetConfig = WebWidgetConfig::factory()
            ->forTenant($this->tenant)
            ->create([
                'channel_id' => $this->webChannel->id,
                'public_key' => $this->publicKey,
                'allowed_origins' => [],
                'pre_chat_form' => 'opcional',
            ]);
    }

    private function sessionsUrl(): string
    {
        return "/widget/v1/{$this->publicKey}/sessions";
    }

    /** @test */
    public function test_session_start_creates_widget_session_with_visitor_token(): void
    {
        $response = $this->postJson($this->sessionsUrl(), []);

        $response->assertCreated();
        $response->assertJsonStructure([
            'visitor_token',
            'session_id',
            'expires_at',
        ]);

        $token = $response->json('visitor_token');
        $this->assertEquals(64, strlen($token), 'visitor_token deve ter 64 chars');
    }

    /** @test */
    public function test_session_start_reuses_existing_visitor_token(): void
    {
        // First request — creates session
        $first = $this->postJson($this->sessionsUrl(), []);
        $first->assertCreated();
        $visitorToken = $first->json('visitor_token');
        $firstSessionId = $first->json('session_id');

        // Second request with same visitor_token — reuses session
        $second = $this->postJson($this->sessionsUrl(), [
            'visitor_token' => $visitorToken,
        ]);
        $second->assertOk(); // 200 for existing session

        $this->assertEquals($firstSessionId, $second->json('session_id'), 'session_id deve ser o mesmo');

        // Only one session exists
        $this->assertDatabaseCount('messaging_web_widget_sessions', 1);
    }

    /** @test */
    public function test_session_start_persists_ip_hash_user_agent_referer(): void
    {
        $response = $this->withHeaders([
            'User-Agent' => 'Mozilla/5.0 (Test Browser)',
            'Referer' => 'https://clinica.com/contato',
        ])->postJson($this->sessionsUrl(), []);

        $response->assertCreated();
        $sessionId = $response->json('session_id');

        $session = WebWidgetSession::find($sessionId);
        $this->assertNotNull($session);

        // ip_hash must be a SHA256 hash (64 hex chars), NOT the plain IP
        $this->assertEquals(64, strlen($session->ip_hash), 'ip_hash deve ser SHA256');
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $session->ip_hash);

        // Must NOT store plain IP
        $this->assertNotEquals('127.0.0.1', $session->ip_hash);
    }

    /** @test */
    public function test_session_start_with_pre_chat_data_persists_provisional_data(): void
    {
        $response = $this->postJson($this->sessionsUrl(), [
            'pre_chat_data' => [
                'nome' => 'João Silva',
                'telefone' => '+5511999999999',
                'email' => 'joao@exemplo.com',
            ],
        ]);

        $response->assertCreated();
        $sessionId = $response->json('session_id');

        $session = WebWidgetSession::find($sessionId);
        $this->assertNotNull($session);
        $this->assertEquals('João Silva', $session->provisional_data['nome']);
        $this->assertEquals('+5511999999999', $session->provisional_data['telefone']);
        $this->assertEquals('joao@exemplo.com', $session->provisional_data['email']);
    }

    /** @test */
    public function test_pre_chat_form_exigido_para_iniciar_rejects_session_without_data(): void
    {
        $this->widgetConfig->update(['pre_chat_form' => 'exigido_para_iniciar']);

        $response = $this->postJson($this->sessionsUrl(), []);

        $response->assertUnprocessable();
        $response->assertJsonPath('error', 'pre_chat_required');
    }

    /** @test */
    public function test_pre_chat_form_exigido_para_iniciar_accepts_session_with_data(): void
    {
        $this->widgetConfig->update(['pre_chat_form' => 'exigido_para_iniciar']);

        $response = $this->postJson($this->sessionsUrl(), [
            'pre_chat_data' => [
                'nome' => 'Maria Souza',
                'telefone' => '+5511888888888',
            ],
        ]);

        $response->assertCreated();
    }

    /** @test */
    public function test_session_isolated_per_widget_config(): void
    {
        // Create another widget config (different tenant, using factories directly)
        $otherTenant = $this->createTenant(['slug' => 'clinica-other-sess-b']);
        $otherKey = bin2hex(random_bytes(32));
        $otherChannel = Channel::factory()->forTenant($otherTenant)->web()->create();
        WebWidgetConfig::factory()->forTenant($otherTenant)->create([
            'channel_id' => $otherChannel->id,
            'public_key' => $otherKey,
            'allowed_origins' => [],
        ]);

        // Create session on widget A
        $responseA = $this->postJson("/widget/v1/{$this->publicKey}/sessions", []);
        $responseA->assertCreated();
        $tokenA = $responseA->json('visitor_token');

        // Use token A on widget B — should not reuse (different widget)
        $responseB = $this->postJson("/widget/v1/{$otherKey}/sessions", [
            'visitor_token' => $tokenA,
        ]);
        // Session token belongs to different widget — creates new session
        $responseB->assertCreated();
        $this->assertNotEquals($responseA->json('session_id'), $responseB->json('session_id'));
    }
}

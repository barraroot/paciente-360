<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Assignment\Models\AiConversationAssignment;
use App\Domain\Ai\Assignment\Services\AiConversationAssignmentService;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesTenantWithRoles;
use Tests\TestCase;

/**
 * US6 / G10 / SC-007 — pausa manual indefinida e reativação; conversa pausada
 * não recebe resposta da IA.
 */
final class AiPauseResumeTest extends TestCase
{
    use CreatesTenantWithRoles;
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedRoles();
        [$this->tenant] = $this->tenantAndUserForRole('ai-pause', 'atendente');
    }

    /**
     * @return array<string, string>
     */
    private function headers(): array
    {
        return ['X-Tenant-Slug' => $this->tenant->slug];
    }

    private function activePersona(): AiPersona
    {
        $model = AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $model->id]);
        AiPersonaChannel::create([
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);

        return $persona;
    }

    private function conversationWithAssignment(AiPersona $persona): Conversation
    {
        $channel = Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'status' => 'ativo',
        ]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $channel->id,
            'ai_paused_until' => null,
        ]);

        app(AiConversationAssignmentService::class)->assign($conversation, $persona, 'whatsapp');

        return $conversation;
    }

    public function test_pause_sets_indefinite_pause_and_marks_assignment_paused(): void
    {
        $persona = $this->activePersona();
        $conversation = $this->conversationWithAssignment($persona);

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, "/ai/conversations/{$conversation->id}/pause"),
        )->assertOk()->assertJsonPath('data.paused', true);

        $conversation->refresh();
        $this->assertNotNull($conversation->ai_paused_until);
        $this->assertTrue($conversation->ai_paused_until->isFuture());
        // Pausa indefinida → muito além de qualquer janela temporizada.
        $this->assertTrue($conversation->ai_paused_until->gt(now()->addYears(10)));

        $this->assertDatabaseHas('ai_conversation_assignments', [
            'conversation_id' => $conversation->id,
            'status' => AiConversationAssignment::STATUS_PAUSED,
        ]);
    }

    public function test_paused_conversation_does_not_get_ai_response(): void
    {
        $persona = $this->activePersona();
        $conversation = $this->conversationWithAssignment($persona);
        $conversation->update(['ai_paused_until' => now()->addCentury()]);

        $message = Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'Olá, tem horário hoje?',
        ]);

        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'Temos sim!', 'intencao' => 'informacao_geral', 'confidence' => 0.95, 'needs_human' => false,
        ]]);

        event(new MensagemRecebida($message, $conversation));

        $this->assertDatabaseMissing('messaging_messages', [
            'conversation_id' => $conversation->id,
            'direction' => 'out',
            'sender_type' => 'ai',
        ]);
        $this->assertDatabaseCount('ai_execution_logs', 0);
    }

    public function test_resume_clears_pause_and_reactivates_assignment(): void
    {
        $persona = $this->activePersona();
        $conversation = $this->conversationWithAssignment($persona);

        app(AiConversationAssignmentService::class)->pauseIndefinitely($conversation, null);

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, "/ai/conversations/{$conversation->id}/resume"),
        )->assertOk()->assertJsonPath('data.paused', false);

        $conversation->refresh();
        $this->assertNull($conversation->ai_paused_until);
        $this->assertDatabaseHas('ai_conversation_assignments', [
            'conversation_id' => $conversation->id,
            'status' => AiConversationAssignment::STATUS_ASSIGNED,
        ]);
    }

    public function test_resume_on_resolved_conversation_is_rejected(): void
    {
        $persona = $this->activePersona();
        $conversation = $this->conversationWithAssignment($persona);
        app(AiConversationAssignmentService::class)->pauseIndefinitely($conversation, null);
        $conversation->update(['status' => 'resolvida']);

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, "/ai/conversations/{$conversation->id}/resume"),
        )->assertStatus(422);
    }

    public function test_state_reports_persona_and_status(): void
    {
        $persona = $this->activePersona();
        $conversation = $this->conversationWithAssignment($persona);

        $this->withHeaders($this->headers())->getJson(
            $this->tenantUrl($this->tenant, "/ai/conversations/{$conversation->id}/state"),
        )->assertOk()
            ->assertJsonPath('data.ai_enabled', true)
            ->assertJsonPath('data.paused', false)
            ->assertJsonPath('data.assignment.status', AiConversationAssignment::STATUS_ASSIGNED)
            ->assertJsonPath('data.assignment.persona.id', $persona->id);
    }

    public function test_requires_inbox_respond_permission(): void
    {
        $persona = $this->activePersona();
        $conversation = $this->conversationWithAssignment($persona);

        // 'financeiro' não tem inbox.respond.
        $noPerm = $this->userForRole($this->tenant, 'financeiro');
        Sanctum::actingAs($noPerm, ['*']);

        $this->withHeaders($this->headers())->postJson(
            $this->tenantUrl($this->tenant, "/ai/conversations/{$conversation->id}/pause"),
        )->assertForbidden();
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Assignment\Services\AiConversationAssignmentService;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US2 / G4 / SC-003 — continuidade: conversa atribuída mantém a mesma persona;
 * canal sem persona ativa não atribui (G5).
 */
final class AssignmentContinuityTest extends TestCase
{
    use RefreshDatabase;

    private AiConversationAssignmentService $service;

    private Tenant $tenant;

    private AiModel $model;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = app(AiConversationAssignmentService::class);
        $this->tenant = Tenant::factory()->create();
        $this->model = AiModel::factory()->create();
    }

    private function personaOnWhatsapp(): AiPersona
    {
        $persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $this->model->id]);
        AiPersonaChannel::create([
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);

        return $persona;
    }

    private ?Channel $sharedChannel = null;

    private function conversation(): Conversation
    {
        // Um único canal WhatsApp por tenant (constraint da Fase 14); múltiplas
        // conversas compartilham o mesmo canal (external_thread_id distinto).
        $this->sharedChannel ??= Channel::factory()->create([
            'tenant_id' => $this->tenant->id,
            'type' => 'whatsapp',
            'status' => 'ativo',
        ]);

        return Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => $this->sharedChannel->id,
        ]);
    }

    public function test_same_conversation_keeps_same_persona(): void
    {
        $this->personaOnWhatsapp();
        $this->personaOnWhatsapp();
        $conversation = $this->conversation();

        $first = $this->service->resolveForConversation($conversation);
        $second = $this->service->resolveForConversation($conversation->fresh());

        $this->assertNotNull($first);
        $this->assertSame($first->id, $second->id);
        $this->assertDatabaseCount('ai_conversation_assignments', 1);
    }

    public function test_returns_null_when_channel_has_no_active_persona(): void
    {
        $conversation = $this->conversation();

        $this->assertNull($this->service->resolveForConversation($conversation));
        $this->assertDatabaseCount('ai_conversation_assignments', 0);
    }

    public function test_distinct_conversations_round_robin_but_stay_stable(): void
    {
        $p1 = $this->personaOnWhatsapp();
        $p2 = $this->personaOnWhatsapp();

        $convA = $this->conversation();
        $convB = $this->conversation();

        $a = $this->service->resolveForConversation($convA);
        $b = $this->service->resolveForConversation($convB);

        // Round-robin entre as duas conversas distintas.
        $this->assertNotSame($a->id, $b->id);

        // Cada conversa permanece com sua persona ao reprocessar.
        $this->assertSame($a->id, $this->service->resolveForConversation($convA->fresh())->id);
        $this->assertSame($b->id, $this->service->resolveForConversation($convB->fresh())->id);
    }
}

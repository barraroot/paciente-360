<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Assignment\Models\AiConversationAssignment;
use App\Domain\Ai\Assignment\Services\AiConversationAssignmentService;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Persona\Services\AiPersonaService;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US6 / G10b / FR-016a — ao desativar/remover uma persona, conversas ativas
 * são reatribuídas a outra persona ativa do canal; sem substituta, a IA é
 * pausada e a conversa escalada para humano.
 */
final class PersonaReassignmentTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);
    }

    private function personaOnWhatsapp(bool $active = true): AiPersona
    {
        $model = AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($this->tenant)->create([
            'ai_model_id' => $model->id,
            'is_active' => $active,
        ]);
        AiPersonaChannel::create([
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);

        return $persona;
    }

    private function conversationAssignedTo(AiPersona $persona): Conversation
    {
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => Channel::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'whatsapp', 'status' => 'ativo'])->id,
            'ai_paused_until' => null,
        ]);
        app(AiConversationAssignmentService::class)->assign($conversation, $persona, 'whatsapp');

        return $conversation;
    }

    public function test_reassigns_to_another_active_persona_on_deactivate(): void
    {
        $personaA = $this->personaOnWhatsapp();
        $personaB = $this->personaOnWhatsapp();
        $conversation = $this->conversationAssignedTo($personaA);

        app(AiPersonaService::class)->deactivate($personaA, null);

        // A conversa passa a apontar para a persona B (única ativa restante).
        $this->assertDatabaseHas('ai_conversation_assignments', [
            'conversation_id' => $conversation->id,
            'ai_persona_id' => $personaB->id,
            'status' => AiConversationAssignment::STATUS_ASSIGNED,
        ]);

        $conversation->refresh();
        $this->assertNull($conversation->ai_paused_until);
    }

    public function test_pauses_and_escalates_when_no_replacement_persona(): void
    {
        $personaA = $this->personaOnWhatsapp();
        $conversation = $this->conversationAssignedTo($personaA);

        app(AiPersonaService::class)->deactivate($personaA, null);

        // Sem outra persona ativa → atribuição pausada + conversa escalada.
        $this->assertDatabaseHas('ai_conversation_assignments', [
            'conversation_id' => $conversation->id,
            'ai_persona_id' => $personaA->id,
            'status' => AiConversationAssignment::STATUS_PAUSED,
        ]);

        $conversation->refresh();
        $this->assertNotNull($conversation->ai_paused_until);
        $this->assertTrue($conversation->ai_paused_until->isFuture());
    }

    public function test_delete_persona_reassigns_then_soft_deletes(): void
    {
        $personaA = $this->personaOnWhatsapp();
        $personaB = $this->personaOnWhatsapp();
        $conversation = $this->conversationAssignedTo($personaA);

        app(AiPersonaService::class)->delete($personaA);

        $this->assertSoftDeleted('ai_personas', ['id' => $personaA->id]);
        $this->assertDatabaseHas('ai_conversation_assignments', [
            'conversation_id' => $conversation->id,
            'ai_persona_id' => $personaB->id,
            'status' => AiConversationAssignment::STATUS_ASSIGNED,
        ]);
    }
}

<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Assignment\Models\AiConversationAssignment;
use App\Domain\Ai\Assignment\Services\AiConversationAssignmentService;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Channel\Models\Channel;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Domain\Messaging\Conversation\Services\HumanTakeoverService;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * US6 / FR-016 — quando um humano assume a conversa (ConversaAssumidaPorHumano),
 * a atribuição de IA é automaticamente marcada como `paused`.
 */
final class HumanTakeoverPauseTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);
    }

    public function test_human_takeover_pauses_active_ai_assignment(): void
    {
        $model = AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $model->id]);
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => Channel::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'whatsapp', 'status' => 'ativo'])->id,
            'ai_paused_until' => null,
        ]);

        app(AiConversationAssignmentService::class)->assign($conversation, $persona, 'whatsapp');

        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        // Fluxo de takeover da Fase 3 emite ConversaAssumidaPorHumano.
        app(HumanTakeoverService::class)->pauseAi($conversation, 30, $user, 'manual_click');

        // O listener da IA marcou a atribuição como pausada.
        $this->assertDatabaseHas('ai_conversation_assignments', [
            'conversation_id' => $conversation->id,
            'status' => AiConversationAssignment::STATUS_PAUSED,
            'paused_by' => $user->id,
        ]);
    }

    public function test_takeover_without_active_assignment_is_noop(): void
    {
        $conversation = Conversation::factory()->create([
            'tenant_id' => $this->tenant->id,
            'channel_id' => Channel::factory()->create(['tenant_id' => $this->tenant->id, 'type' => 'whatsapp', 'status' => 'ativo'])->id,
            'ai_paused_until' => null,
        ]);
        $user = User::factory()->create(['tenant_id' => $this->tenant->id]);

        app(HumanTakeoverService::class)->pauseAi($conversation, 30, $user, 'manual_click');

        $this->assertDatabaseCount('ai_conversation_assignments', 0);
    }
}

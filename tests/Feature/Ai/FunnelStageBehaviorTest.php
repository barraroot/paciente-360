<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Context\Models\ConversationSummary;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Support\AiConversationFactory;
use Tests\TestCase;

/**
 * Feature 017 (US4, FR-018) — a etapa atual do funil é injetada no prompt para
 * manter a coerência do fluxo comercial.
 */
final class FunnelStageBehaviorTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);

        $model = AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $model->id]);
        AiPersonaChannel::create([
            'tenant_id' => $this->tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);

        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'Perfeito! ✨',
            'intencao' => 'agendamento',
            'confidence' => 0.95,
            'needs_human' => false,
        ]]);
    }

    public function test_current_funnel_stage_is_injected_into_prompt(): void
    {
        $conversation = AiConversationFactory::conversation($this->tenant);
        ConversationSummary::factory()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'funnel_stage' => 'pricing',
        ]);

        $current = Message::factory()->inbound()->create([
            'tenant_id' => $this->tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'quero agendar',
        ]);

        event(new MensagemRecebida($current, $conversation));

        PersonaAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $instructions = (string) $prompt->agent->instructions();

            return str_contains($instructions, 'Etapa atual do funil: pricing')
                && str_contains($instructions, 'não cote preço antes de qualificar');
        });
    }
}

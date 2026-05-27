<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Agents\PersonaAgent;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\WorkContext\Models\AiWorkContext;
use App\Domain\Ai\WorkContext\Services\AiWorkContextService;
use App\Domain\Messaging\Message\Events\MensagemRecebida;
use App\Domain\Messaging\Message\Models\Message;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Laravel\Ai\Prompts\AgentPrompt;
use Tests\Support\AiConversationFactory;
use Tests\TestCase;

/**
 * Feature 017 (US2) — T018: o Contexto de Trabalho é injetado no system prompt
 * (tom, valores, perguntas) e cada clínica reflete só os próprios fatos.
 */
final class WorkContextAppliedToPromptTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Ai::fakeAgent(PersonaAgent::class, [[
            'resposta' => 'Claro! 💛',
            'intencao' => 'informacao_geral',
            'confidence' => 0.95,
            'needs_human' => false,
        ]]);
    }

    private function bootTenant(string $price, string $tone): Tenant
    {
        $tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $tenant);

        // AiModel é catálogo GLOBAL (não por tenant) — reusa um único para evitar
        // colisão de identificador único ao montar mais de uma clínica.
        $model = AiModel::query()->first() ?? AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($tenant)->create(['ai_model_id' => $model->id]);
        AiPersonaChannel::create([
            'tenant_id' => $tenant->id,
            'ai_persona_id' => $persona->id,
            'channel_type' => 'whatsapp',
            'is_active' => true,
        ]);

        AiWorkContext::factory()->create([
            'tenant_id' => $tenant->id,
            'tone' => $tone,
            'pricing' => [['item' => 'Consulta', 'valor_a_vista' => $price]],
            'qualification_questions' => ['Com que frequência acontecem as crises?'],
        ]);

        return $tenant;
    }

    private function fireInbound(Tenant $tenant): void
    {
        $this->app->instance('tenant', $tenant);
        $conversation = AiConversationFactory::conversation($tenant);
        $message = Message::factory()->inbound()->create([
            'tenant_id' => $tenant->id,
            'conversation_id' => $conversation->id,
            'body' => 'Qual o valor da consulta?',
        ]);
        event(new MensagemRecebida($message, $conversation));
    }

    public function test_work_context_is_injected_into_prompt(): void
    {
        $tenant = $this->bootTenant('R$300', 'acolhedor, com emojis 💛');
        $this->fireInbound($tenant);

        PersonaAgent::assertPrompted(function (AgentPrompt $prompt): bool {
            $instructions = (string) $prompt->agent->instructions();

            return str_contains($instructions, 'R$300')
                && str_contains($instructions, 'acolhedor, com emojis')
                && str_contains($instructions, 'Com que frequência acontecem as crises?')
                && str_contains($instructions, 'UMA por vez');
        });
    }

    public function test_each_clinic_uses_only_its_own_context(): void
    {
        // Determinístico no nível do serviço (sem dois fluxos end-to-end na mesma
        // transação de teste): cada clínica renderiza apenas os próprios fatos.
        $service = app(AiWorkContextService::class);

        $tenantA = $this->bootTenant('R$300', 'tom da clínica A');
        $tenantB = $this->bootTenant('R$555', 'tom da clínica B');

        $this->app->instance('tenant', $tenantA);
        $renderedA = $service->renderForPrompt($service->getForTenant($tenantA->id));

        $this->app->instance('tenant', $tenantB);
        $renderedB = $service->renderForPrompt($service->getForTenant($tenantB->id));

        $this->assertStringContainsString('R$300', $renderedA);
        $this->assertStringNotContainsString('R$555', $renderedA);

        $this->assertStringContainsString('R$555', $renderedB);
        $this->assertStringNotContainsString('R$300', $renderedB);
    }
}

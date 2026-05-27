<?php

declare(strict_types=1);

namespace Tests\Unit\Ai;

use App\Domain\Ai\Context\Services\ConversationHistoryAssembler;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Messages\MessageRole;
use Tests\Support\AiConversationFactory;
use Tests\TestCase;

/**
 * Feature 017 (US1) — T008: a janela verbatim mínima é montada corretamente,
 * com papéis mapeados, PII pseudonimizada e respeitando o tamanho configurado.
 */
final class ConversationHistoryAssemblerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);
    }

    public function test_maps_roles_and_orders_chronologically(): void
    {
        config(['ai.matricial.history.window_messages' => 6]);
        $conversation = AiConversationFactory::conversation($this->tenant);
        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'Enxaqueca'],
            ['role' => 'ai', 'body' => 'Com que frequência acontecem as crises?'],
            ['role' => 'patient', 'body' => 'Quase todo dia'],
        ]);

        $messages = app(ConversationHistoryAssembler::class)->assemble($conversation);

        $this->assertCount(3, $messages);
        $this->assertSame(MessageRole::User, $messages[0]->role);
        $this->assertSame(MessageRole::Assistant, $messages[1]->role);
        $this->assertSame(MessageRole::User, $messages[2]->role);
        $this->assertSame('Enxaqueca', $messages[0]->content);
    }

    public function test_respects_window_size(): void
    {
        config(['ai.matricial.history.window_messages' => 4]);
        $conversation = AiConversationFactory::conversation($this->tenant);
        $turns = [];
        for ($i = 1; $i <= 10; $i++) {
            $turns[] = ['role' => $i % 2 === 1 ? 'patient' : 'ai', 'body' => "msg {$i}"];
        }
        AiConversationFactory::seedTurns($this->tenant, $conversation, $turns);

        $messages = app(ConversationHistoryAssembler::class)->assemble($conversation);

        $this->assertCount(4, $messages);
        // Mantém as 4 MAIS RECENTES, em ordem cronológica.
        $this->assertSame('msg 7', $messages[0]->content);
        $this->assertSame('msg 10', $messages[3]->content);
    }

    public function test_excludes_current_message(): void
    {
        config(['ai.matricial.history.window_messages' => 6]);
        $conversation = AiConversationFactory::conversation($this->tenant);
        $seeded = AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'Oi'],
            ['role' => 'ai', 'body' => 'Olá! Qual sua queixa?'],
            ['role' => 'patient', 'body' => 'Qual o valor?'],
        ]);
        $current = $seeded->last();

        $messages = app(ConversationHistoryAssembler::class)->assemble($conversation, $current->id);

        $this->assertCount(2, $messages);
        $this->assertSame('Olá! Qual sua queixa?', $messages[1]->content);
    }

    public function test_pseudonymizes_pii_in_history(): void
    {
        config(['ai.matricial.history.window_messages' => 6]);
        $conversation = AiConversationFactory::conversation($this->tenant);
        AiConversationFactory::seedTurns($this->tenant, $conversation, [
            ['role' => 'patient', 'body' => 'meu email é joao@example.com'],
        ]);

        $messages = app(ConversationHistoryAssembler::class)->assemble($conversation);

        $this->assertStringNotContainsString('joao@example.com', $messages[0]->content);
        $this->assertStringContainsString('<email>', $messages[0]->content);
    }
}

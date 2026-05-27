<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\Context\Agents\ConversationSummarizerAgent;
use App\Domain\Ai\Context\Models\ConversationSummary;
use App\Domain\Ai\Context\Services\ConversationSummarizerService;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Ai;
use Tests\Support\AiConversationFactory;
use Tests\TestCase;

/**
 * Feature 017 (US3, FR-002b/022) — resumo rolante incremental: só roda quando há
 * turnos além da janela; reusa quando nada mudou.
 */
final class ConversationSummarizerTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    private AiPersona $persona;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);

        config(['ai.matricial.history.window_messages' => 6]);

        $model = AiModel::factory()->create();
        $this->persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $model->id]);

        Ai::fakeAgent(ConversationSummarizerAgent::class, [[
            'summary' => 'Paciente com enxaqueca quase diária; interessado na consulta.',
            'funnel_stage' => 'qualifying',
        ]]);
    }

    /**
     * @return array<int, array{role: string, body: string}>
     */
    private function turns(int $n): array
    {
        $out = [];
        for ($i = 1; $i <= $n; $i++) {
            $out[] = ['role' => $i % 2 === 1 ? 'patient' : 'ai', 'body' => "mensagem {$i}"];
        }

        return $out;
    }

    public function test_does_not_summarize_when_within_window(): void
    {
        $conversation = AiConversationFactory::conversation($this->tenant);
        AiConversationFactory::seedTurns($this->tenant, $conversation, $this->turns(4));

        $result = app(ConversationSummarizerService::class)->maybeSummarize($conversation, $this->persona);

        $this->assertNull($result);
        $this->assertDatabaseCount('ai_conversation_summaries', 0);
    }

    public function test_summarizes_messages_beyond_window(): void
    {
        $conversation = AiConversationFactory::conversation($this->tenant);
        $seeded = AiConversationFactory::seedTurns($this->tenant, $conversation, $this->turns(9));
        // older = primeiras 3 (9 - janela 6); cobre até a 3ª mensagem.
        $expectedCover = $seeded[2]->id;

        $summary = app(ConversationSummarizerService::class)->maybeSummarize($conversation, $this->persona);

        $this->assertNotNull($summary);
        $this->assertSame('qualifying', $summary->funnel_stage);
        $this->assertStringContainsString('enxaqueca quase diária', $summary->summary_text);
        $this->assertSame($expectedCover, (int) $summary->covered_up_to_message_id);
        $this->assertDatabaseCount('ai_conversation_summaries', 1);
    }

    public function test_reuses_existing_summary_when_nothing_new(): void
    {
        $conversation = AiConversationFactory::conversation($this->tenant);
        AiConversationFactory::seedTurns($this->tenant, $conversation, $this->turns(9));

        $service = app(ConversationSummarizerService::class);
        $first = $service->maybeSummarize($conversation, $this->persona);
        $second = $service->maybeSummarize($conversation, $this->persona);

        // Sem mensagens novas → reusa (mesma versão, sem nova chamada ao modelo).
        $this->assertSame($first->version, $second->version);
        $this->assertSame(1, (int) ConversationSummary::query()->where('conversation_id', $conversation->id)->first()->version);
    }
}

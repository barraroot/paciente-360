<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeChunk;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Ai\Services\AiContextBuilderService;
use App\Domain\Ai\Services\AiEmbeddingService;
use App\Domain\Ai\Services\AiKnowledgeRetrievalService;
use App\Jobs\Ai\EmbedKnowledgeBaseJob;
use App\Models\Tenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Ai\Embeddings;
use Laravel\Ai\Prompts\EmbeddingsPrompt;
use Tests\TestCase;

/**
 * US4 / G8 / SC-012 — recuperação RAG só de bases ATIVAS associadas à persona,
 * com isolamento de tenant na própria query de similaridade.
 *
 * Embeddings fakeados de forma determinística: cada trecho vira um vetor
 * unitário num eixo definido por palavra-chave, tornando a similaridade de
 * cosseno previsível (idêntico → 1.0, ortogonal → 0.0).
 */
final class RagRetrievalTest extends TestCase
{
    use RefreshDatabase;

    private Tenant $tenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tenant = Tenant::factory()->create();
        $this->app->instance('tenant', $this->tenant);

        $this->fakeDeterministicEmbeddings();
    }

    private function fakeDeterministicEmbeddings(): void
    {
        $dimension = (int) config('ai.matricial.embedding_dimension', 1536);

        Embeddings::fake(function (EmbeddingsPrompt $prompt) use ($dimension): array {
            return array_map(function (string $input) use ($dimension): array {
                $vector = array_fill(0, $dimension, 0.0);
                $text = mb_strtolower($input);

                $axis = match (true) {
                    str_contains($text, 'horário') || str_contains($text, 'horario') => 0,
                    str_contains($text, 'estacionamento') => 1,
                    default => 2,
                };

                $vector[$axis] = 1.0;

                return $vector;
            }, $prompt->inputs);
        });
    }

    private function indexedBase(Tenant $tenant, string $content, bool $active = true): AiKnowledgeBase
    {
        $base = AiKnowledgeBase::factory()->forTenant($tenant)->create([
            'markdown_content' => $content,
            'is_active' => $active,
        ]);

        // Indexa de forma síncrona (o job restaura o contexto de tenant).
        (new EmbedKnowledgeBaseJob($base->id, $tenant->id))->handle(app(AiEmbeddingService::class));

        return $base->refresh();
    }

    private function personaWith(AiKnowledgeBase ...$bases): AiPersona
    {
        $model = AiModel::factory()->create();
        $persona = AiPersona::factory()->forTenant($this->tenant)->create(['ai_model_id' => $model->id]);
        $persona->knowledgeBases()->sync($persona->pivotTenantMap(collect($bases)->pluck('id')->all()));

        return $persona;
    }

    public function test_indexing_creates_chunks_with_embeddings(): void
    {
        $base = $this->indexedBase($this->tenant, '# Horários\n\nAtendemos em horário comercial.');

        $this->assertNotNull($base->indexed_at);
        $this->assertDatabaseHas('ai_knowledge_chunks', [
            'ai_knowledge_base_id' => $base->id,
            'tenant_id' => $this->tenant->id,
        ]);
    }

    public function test_retrieves_only_from_active_associated_bases(): void
    {
        $active = $this->indexedBase($this->tenant, '# Horários\n\nAtendemos em horário comercial das 8h às 18h.');
        $inactive = $this->indexedBase($this->tenant, '# Horários\n\nhorário antigo desativado.', active: false);

        $persona = $this->personaWith($active, $inactive);

        $hits = app(AiKnowledgeRetrievalService::class)->retrieve($persona, 'qual o horário de atendimento?');

        $this->assertNotEmpty($hits);
        $contents = array_column($hits, 'content');
        $this->assertStringContainsString('comercial', implode(' ', $contents));
        $this->assertStringNotContainsString('desativado', implode(' ', $contents));
    }

    public function test_does_not_retrieve_other_tenant_chunks(): void
    {
        $otherTenant = Tenant::factory()->create();
        $this->app->instance('tenant', $otherTenant);
        $otherBase = $this->indexedBase($otherTenant, '# Horários\n\nhorário de outra clínica.');

        // Volta para o tenant atual.
        $this->app->instance('tenant', $this->tenant);
        $mine = $this->indexedBase($this->tenant, '# Horários\n\nmeu horário comercial.');
        $persona = $this->personaWith($mine);

        $hits = app(AiKnowledgeRetrievalService::class)->retrieve($persona, 'qual o horário?');

        // O chunk do outro tenant existe no banco, mas NÃO é recuperado.
        $this->assertDatabaseHas('ai_knowledge_chunks', ['ai_knowledge_base_id' => $otherBase->id]);
        $ids = array_column($hits, 'id');
        $otherChunkIds = AiKnowledgeChunk::withoutGlobalScopes()
            ->where('ai_knowledge_base_id', $otherBase->id)
            ->pluck('id')
            ->all();
        $this->assertEmpty(array_intersect($ids, $otherChunkIds));
    }

    public function test_below_threshold_returns_nothing(): void
    {
        $base = $this->indexedBase($this->tenant, '# Horários\n\nhorário comercial.');
        $persona = $this->personaWith($base);

        // Consulta ortogonal (estacionamento → eixo diferente) → similaridade 0.
        $hits = app(AiKnowledgeRetrievalService::class)->retrieve($persona, 'tem estacionamento no local?');

        $this->assertEmpty($hits);
    }

    public function test_context_builder_injects_rag_snippets(): void
    {
        $base = $this->indexedBase($this->tenant, '# Horários\n\nAtendemos em horário comercial.');
        $persona = $this->personaWith($base);
        $persona->setRelation('guardrails', collect());

        $context = app(AiContextBuilderService::class)->build($persona, 'qual o horário?');

        $this->assertNotEmpty($context->ragSnippetIds);
        $this->assertStringContainsString('Base de Conhecimento', $context->instructions);
        $this->assertStringContainsString('comercial', $context->instructions);
    }
}

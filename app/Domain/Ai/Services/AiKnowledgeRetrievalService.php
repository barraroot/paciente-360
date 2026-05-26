<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

use App\Domain\Ai\Persona\Models\AiPersona;
use Illuminate\Support\Facades\DB;

/**
 * Recuperação semântica (RAG) para uma persona: embeda a mensagem do paciente
 * e busca os top-K trechos mais similares APENAS entre as bases ATIVAS
 * associadas à persona, filtrando por `tenant_id` na própria query de
 * similaridade (Princípio II / G8 / SC-012).
 */
final class AiKnowledgeRetrievalService
{
    public function __construct(private readonly AiEmbeddingService $embeddings) {}

    /**
     * @return list<array{id: int, content: string, similarity: float}>
     */
    public function retrieve(AiPersona $persona, string $query): array
    {
        $baseIds = $persona->knowledgeBases()
            ->where('ai_knowledge_bases.is_active', true)
            ->pluck('ai_knowledge_bases.id')
            ->all();

        if ($baseIds === [] || trim($query) === '') {
            return [];
        }

        $queryEmbedding = $this->embeddings->embedQuery($query);
        if ($queryEmbedding === []) {
            return [];
        }

        $literal = '['.implode(',', array_map(static fn ($v): string => (string) (float) $v, $queryEmbedding)).']';
        $topK = max(1, (int) config('ai.matricial.rag.top_k', 6));
        $minSimilarity = (float) config('ai.matricial.rag.min_similarity', 0.2);
        $tenantId = $persona->tenant_id;

        $placeholders = implode(',', array_fill(0, count($baseIds), '?'));

        // similaridade de cosseno = 1 - distância (`<=>`).
        $rows = DB::select(
            <<<SQL
            SELECT id, content, (1 - (embedding <=> ?::vector)) AS similarity
            FROM ai_knowledge_chunks
            WHERE tenant_id = ?
              AND ai_knowledge_base_id IN ({$placeholders})
              AND embedding IS NOT NULL
              AND (1 - (embedding <=> ?::vector)) >= ?
            ORDER BY embedding <=> ?::vector ASC
            LIMIT ?
            SQL,
            [$literal, $tenantId, ...$baseIds, $literal, $minSimilarity, $literal, $topK],
        );

        return array_map(static fn (object $row): array => [
            'id' => (int) $row->id,
            'content' => (string) $row->content,
            'similarity' => (float) $row->similarity,
        ], $rows);
    }
}

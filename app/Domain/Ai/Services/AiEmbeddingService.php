<?php

declare(strict_types=1);

namespace App\Domain\Ai\Services;

use Laravel\Ai\Embeddings;

/**
 * Chunking determinístico do Markdown das bases e geração de embeddings via
 * Laravel AI SDK (R1/R2/R10). Os vetores são persistidos no nosso
 * `ai_knowledge_chunks` (pgvector, tenant-scoped) pelo EmbedKnowledgeBaseJob —
 * o isolamento multi-tenant fica na própria query de similaridade (Princípio II).
 */
final class AiEmbeddingService
{
    /**
     * Fragmenta o conteúdo por seções Markdown e, dentro de cada seção, por
     * janelas de tamanho-alvo com sobreposição. Determinístico (testável).
     *
     * @return list<array{index: int, content: string, token_count: int}>
     */
    public function chunk(string $markdown): array
    {
        $text = trim($markdown);
        if ($text === '') {
            return [];
        }

        $size = max(200, (int) config('ai.matricial.rag.chunk_chars', 1200));
        $overlap = max(0, min((int) config('ai.matricial.rag.chunk_overlap', 150), $size - 1));
        $step = $size - $overlap;

        $chunks = [];
        $index = 0;

        foreach ($this->splitIntoSections($text) as $section) {
            $length = mb_strlen($section);
            $start = 0;

            while ($start < $length) {
                $piece = trim(mb_substr($section, $start, $size));

                if ($piece !== '') {
                    $chunks[] = [
                        'index' => $index++,
                        'content' => $piece,
                        'token_count' => $this->estimateTokens($piece),
                    ];
                }

                if ($start + $size >= $length) {
                    break;
                }

                $start += $step;
            }
        }

        return $chunks;
    }

    /**
     * Gera os embeddings de uma lista de trechos (batch).
     *
     * @param list<string> $inputs
     * @return list<list<float>>
     */
    public function embed(array $inputs): array
    {
        if ($inputs === []) {
            return [];
        }

        $dimension = (int) config('ai.matricial.embedding_dimension', 1536);

        $response = Embeddings::for(array_values($inputs))
            ->dimensions($dimension)
            ->generate();

        /** @var list<list<float>> $embeddings */
        $embeddings = $response->embeddings;

        return $embeddings;
    }

    /**
     * Embedding único (ex.: a mensagem do paciente na recuperação).
     *
     * @return list<float>
     */
    public function embedQuery(string $query): array
    {
        return $this->embed([$query])[0] ?? [];
    }

    /**
     * Quebra o texto em seções por cabeçalhos Markdown (H1–H6), mantendo o
     * cabeçalho junto do corpo. Sem cabeçalhos, retorna o texto inteiro.
     *
     * @return list<string>
     */
    private function splitIntoSections(string $text): array
    {
        $lines = preg_split('/\R/u', $text) ?: [$text];
        $sections = [];
        $current = '';

        foreach ($lines as $line) {
            if (preg_match('/^#{1,6}\s/u', $line) === 1 && trim($current) !== '') {
                $sections[] = trim($current);
                $current = '';
            }

            $current .= $line."\n";
        }

        if (trim($current) !== '') {
            $sections[] = trim($current);
        }

        return $sections === [] ? [$text] : $sections;
    }

    private function estimateTokens(string $text): int
    {
        // Heurística barata (~4 chars/token) — suficiente para auditoria/custo.
        return (int) max(1, ceil(mb_strlen($text) / 4));
    }
}

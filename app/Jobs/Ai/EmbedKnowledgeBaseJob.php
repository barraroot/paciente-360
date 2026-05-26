<?php

declare(strict_types=1);

namespace App\Jobs\Ai;

use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeChunk;
use App\Domain\Ai\Services\AiEmbeddingService;
use App\Models\Tenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

/**
 * (Re)indexa uma base de conhecimento (R10): fragmenta o Markdown, gera os
 * embeddings (Laravel AI SDK) e SUBSTITUI transacionalmente os
 * `ai_knowledge_chunks` da base. Ao concluir, marca `indexed_at`.
 *
 * Ativar/desativar uma base NÃO dispara este job (apenas alterna `is_active`,
 * filtrado na recuperação — FR-021b).
 */
final class EmbedKnowledgeBaseJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public function __construct(
        public readonly int $knowledgeBaseId,
        public readonly int $tenantId,
    ) {
        $this->onQueue(config('ai.matricial.queue', 'ai'));
    }

    public function handle(AiEmbeddingService $embeddings): void
    {
        $this->restoreTenantContext();

        $base = AiKnowledgeBase::find($this->knowledgeBaseId);
        if ($base === null) {
            return;
        }

        $chunks = $embeddings->chunk((string) $base->markdown_content);

        if ($chunks === []) {
            DB::transaction(function () use ($base): void {
                AiKnowledgeChunk::where('ai_knowledge_base_id', $base->id)->delete();
                $base->forceFill(['indexed_at' => now()])->saveQuietly();
            });

            return;
        }

        $vectors = $embeddings->embed(array_map(static fn (array $c): string => $c['content'], $chunks));

        DB::transaction(function () use ($base, $chunks, $vectors): void {
            AiKnowledgeChunk::where('ai_knowledge_base_id', $base->id)->delete();

            foreach ($chunks as $i => $chunk) {
                $vector = $vectors[$i] ?? null;
                if ($vector === null) {
                    continue;
                }

                $literal = '['.implode(',', array_map(static fn ($v): string => (string) (float) $v, $vector)).']';

                DB::insert(
                    'INSERT INTO ai_knowledge_chunks
                        (tenant_id, ai_knowledge_base_id, chunk_index, content, token_count, embedding, created_at, updated_at)
                     VALUES (?, ?, ?, ?, ?, ?::vector, now(), now())',
                    [$base->tenant_id, $base->id, $chunk['index'], $chunk['content'], $chunk['token_count'], $literal],
                );
            }

            $base->forceFill(['indexed_at' => now()])->saveQuietly();
        });
    }

    private function restoreTenantContext(): void
    {
        $tenant = Tenant::find($this->tenantId);
        if ($tenant === null) {
            return;
        }

        app()->instance('tenant', $tenant);
        app(PermissionRegistrar::class)->setPermissionsTeamId($tenant->id);
    }
}

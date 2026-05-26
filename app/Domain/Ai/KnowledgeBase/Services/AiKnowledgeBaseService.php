<?php

declare(strict_types=1);

namespace App\Domain\Ai\KnowledgeBase\Services;

use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use App\Jobs\Ai\EmbedKnowledgeBaseJob;

/**
 * Regras de negócio das bases de conhecimento (FR-019..FR-021b).
 * `is_active` muda apenas via activate()/deactivate(); criar/editar conteúdo
 * dispara (re)indexação assíncrona (R10).
 */
final class AiKnowledgeBaseService
{
    /**
     * @param array<string, mixed> $data
     */
    public function create(array $data, int $tenantId, ?int $userId): AiKnowledgeBase
    {
        $base = AiKnowledgeBase::create([
            ...$data,
            'tenant_id' => $tenantId,
            'is_active' => true,
            'indexed_at' => null,
            'created_by' => $userId,
            'updated_by' => $userId,
        ]);

        $this->reindex($base);

        return $base;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function update(AiKnowledgeBase $base, array $data, ?int $userId): AiKnowledgeBase
    {
        unset($data['is_active'], $data['tenant_id']);

        $contentChanged = array_key_exists('markdown_content', $data)
            && $data['markdown_content'] !== $base->markdown_content;

        $base->fill($data);
        $base->updated_by = $userId;
        $base->save();

        if ($contentChanged) {
            $this->reindex($base->refresh());
        }

        return $base->refresh();
    }

    public function activate(AiKnowledgeBase $base, ?int $userId): AiKnowledgeBase
    {
        if (! $base->is_active) {
            $base->update(['is_active' => true, 'updated_by' => $userId]);
        }

        return $base;
    }

    public function deactivate(AiKnowledgeBase $base, ?int $userId): AiKnowledgeBase
    {
        if ($base->is_active) {
            $base->update(['is_active' => false, 'updated_by' => $userId]);
        }

        return $base;
    }

    private function reindex(AiKnowledgeBase $base): void
    {
        EmbedKnowledgeBaseJob::dispatch($base->id, $base->tenant_id);
    }
}

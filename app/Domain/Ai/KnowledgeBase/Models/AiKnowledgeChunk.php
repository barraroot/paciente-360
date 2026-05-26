<?php

namespace App\Domain\Ai\KnowledgeBase\Models;

use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Ai\AiKnowledgeChunkFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Fragmento vetorizado de uma base (RAG). A coluna `embedding` é do tipo
 * pgvector; é lida/gravada via expressões SQL no AiEmbeddingService, por isso
 * não é fillable nem cast aqui.
 */
class AiKnowledgeChunk extends Model
{
    /** @use HasFactory<AiKnowledgeChunkFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'ai_knowledge_chunks';

    protected static function newFactory(): AiKnowledgeChunkFactory
    {
        return AiKnowledgeChunkFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'ai_knowledge_base_id',
        'chunk_index',
        'content',
        'token_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'chunk_index' => 'integer',
            'token_count' => 'integer',
        ];
    }

    public function knowledgeBase(): BelongsTo
    {
        return $this->belongsTo(AiKnowledgeBase::class, 'ai_knowledge_base_id');
    }
}

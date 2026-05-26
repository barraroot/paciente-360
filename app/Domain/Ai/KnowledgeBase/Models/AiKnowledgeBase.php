<?php

namespace App\Domain\Ai\KnowledgeBase\Models;

use App\Casts\AsJsonArray;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Ai\AiKnowledgeBaseFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiKnowledgeBase extends Model
{
    /** @use HasFactory<AiKnowledgeBaseFactory> */
    use BelongsToTenant;

    use HasFactory;
    use SoftDeletes;

    protected $table = 'ai_knowledge_bases';

    protected static function newFactory(): AiKnowledgeBaseFactory
    {
        return AiKnowledgeBaseFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'markdown_content',
        'tags',
        'metadata',
        'is_active',
        'indexed_at',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'tags' => AsJsonArray::class,
            'metadata' => AsJsonArray::class,
            'is_active' => 'boolean',
            'indexed_at' => 'datetime',
        ];
    }

    public function chunks(): HasMany
    {
        return $this->hasMany(AiKnowledgeChunk::class);
    }

    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(AiPersona::class, 'ai_persona_knowledge_base')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

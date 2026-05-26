<?php

namespace App\Domain\Ai\Model\Models;

use App\Casts\AsJsonArray;
use App\Domain\Ai\Persona\Models\AiPersona;
use Database\Factories\Ai\AiModelFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Catálogo GLOBAL de modelos de IA da plataforma (sem tenant).
 * Gerido pelo super-admin; tenant apenas seleciona ativos.
 */
class AiModel extends Model
{
    /** @use HasFactory<AiModelFactory> */
    use HasFactory;

    protected $table = 'ai_models';

    protected static function newFactory(): AiModelFactory
    {
        return AiModelFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'name',
        'provider',
        'internal_identifier',
        'description',
        'config_schema',
        'supports_embedding',
        'embedding_dimension',
        'is_active',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'config_schema' => AsJsonArray::class,
            'supports_embedding' => 'boolean',
            'embedding_dimension' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    public function personas(): HasMany
    {
        return $this->hasMany(AiPersona::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

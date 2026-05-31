<?php

namespace App\Domain\Ai\Persona\Models;

use App\Casts\AsJsonArray;
use App\Domain\Ai\Guardrail\Models\AiGuardrail;
use App\Domain\Ai\KnowledgeBase\Models\AiKnowledgeBase;
use App\Domain\Ai\Matrix\Models\AiPersonaChannel;
use App\Domain\Ai\Model\Models\AiModel;
use App\Domain\Ai\Voice\Models\VoiceCatalogEntry;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Ai\AiPersonaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiPersona extends Model
{
    /** @use HasFactory<AiPersonaFactory> */
    use BelongsToTenant;

    use HasFactory;
    use SoftDeletes;

    protected $table = 'ai_personas';

    protected static function newFactory(): AiPersonaFactory
    {
        return AiPersonaFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'ai_model_id',
        'voice_id',
        'name',
        'description',
        'markdown_content',
        'tone',
        'objective',
        'limitations',
        'initial_message',
        'fallback_message',
        'handoff_rules',
        'model_settings',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'model_settings' => AsJsonArray::class,
            'is_active' => 'boolean',
        ];
    }

    public function model(): BelongsTo
    {
        return $this->belongsTo(AiModel::class, 'ai_model_id');
    }

    /**
     * Feature 018 (US5, Q-clarify-4=B) — voz desta Persona no TTS (FR-037a).
     * NULL → fallback no PersonaVoiceResolverService (T146).
     *
     * @return BelongsTo<VoiceCatalogEntry, $this>
     */
    public function voice(): BelongsTo
    {
        return $this->belongsTo(VoiceCatalogEntry::class, 'voice_id');
    }

    public function channels(): HasMany
    {
        return $this->hasMany(AiPersonaChannel::class);
    }

    public function knowledgeBases(): BelongsToMany
    {
        return $this->belongsToMany(AiKnowledgeBase::class, 'ai_persona_knowledge_base')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    public function guardrails(): BelongsToMany
    {
        return $this->belongsToMany(AiGuardrail::class, 'ai_persona_guardrail')
            ->withPivot('tenant_id')
            ->withTimestamps();
    }

    /**
     * Mapa para `sync()` que preenche o `tenant_id` do pivot (NOT NULL).
     *
     * @param list<int> $ids
     * @return array<int, array{tenant_id: int|null}>
     */
    public function pivotTenantMap(array $ids): array
    {
        return collect($ids)
            ->mapWithKeys(fn (int $id): array => [$id => ['tenant_id' => $this->tenant_id]])
            ->all();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

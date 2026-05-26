<?php

namespace App\Domain\Ai\Guardrail\Models;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Concerns\BelongsToTenant;
use Database\Factories\Ai\AiGuardrailFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class AiGuardrail extends Model
{
    /** @use HasFactory<AiGuardrailFactory> */
    use BelongsToTenant;

    use HasFactory;
    use SoftDeletes;

    protected $table = 'ai_guardrails';

    protected static function newFactory(): AiGuardrailFactory
    {
        return AiGuardrailFactory::new();
    }

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'name',
        'description',
        'category',
        'markdown_content',
        'is_active',
        'created_by',
        'updated_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function personas(): BelongsToMany
    {
        return $this->belongsToMany(AiPersona::class, 'ai_persona_guardrail')
            ->withTimestamps();
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Domain\Ai\Matrix\Models;

use App\Domain\Ai\Persona\Models\AiPersona;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiPersonaChannel extends Model
{
    use BelongsToTenant;

    protected $table = 'ai_persona_channels';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'ai_persona_id',
        'channel_type',
        'is_active',
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

    public function persona(): BelongsTo
    {
        return $this->belongsTo(AiPersona::class, 'ai_persona_id');
    }

    public function scopeForChannel(Builder $query, string $channelType): Builder
    {
        return $query->where('channel_type', $channelType);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('is_active', true);
    }
}

<?php

namespace App\Domain\Ai\Assignment\Models;

use App\Casts\AsJsonArray;
use App\Domain\Ai\Persona\Models\AiPersona;
use App\Domain\Messaging\Conversation\Models\Conversation;
use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AiConversationAssignment extends Model
{
    use BelongsToTenant;

    protected $table = 'ai_conversation_assignments';

    public const STATUS_ASSIGNED = 'assigned';

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_PAUSED = 'paused';

    public const STATUS_CLOSED = 'closed';

    public const STATUS_ERROR = 'error';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'channel_type',
        'ai_persona_id',
        'status',
        'assigned_at',
        'unassigned_at',
        'paused_by',
        'paused_at',
        'metadata',
    ];

    /**
     * @return array<string, string|class-string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
            'paused_at' => 'datetime',
            'metadata' => AsJsonArray::class,
        ];
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class, 'conversation_id');
    }

    public function persona(): BelongsTo
    {
        return $this->belongsTo(AiPersona::class, 'ai_persona_id');
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->where('status', '!=', self::STATUS_CLOSED);
    }

    public function isPaused(): bool
    {
        return $this->status === self::STATUS_PAUSED;
    }
}

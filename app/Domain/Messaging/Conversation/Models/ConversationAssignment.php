<?php

namespace App\Domain\Messaging\Conversation\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\Messaging\ConversationAssignmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T029 — Model `ConversationAssignment`: histórico de atribuições (append-only).
 *
 * Cada transferência/atribuição cria uma nova linha. `unassigned_at` é preenchido
 * quando a próxima atribuição ocorre. Sem UPDATE de conteúdo (padrão imutável).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $conversation_id
 * @property int|null $user_id
 * @property int|null $assigned_by
 * @property string|null $assignment_role
 * @property Carbon $assigned_at
 * @property Carbon|null $unassigned_at
 * @property string|null $transfer_note
 * @property string|null $reason
 */
class ConversationAssignment extends Model
{
    /** @use HasFactory<ConversationAssignmentFactory> */
    use BelongsToTenant;

    use HasFactory;

    protected $table = 'messaging_conversation_assignments';

    protected static function newFactory(): ConversationAssignmentFactory
    {
        return ConversationAssignmentFactory::new();
    }

    /** @var bool */
    public $timestamps = false;

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'conversation_id',
        'user_id',
        'assigned_by',
        'assignment_role',
        'assigned_at',
        'unassigned_at',
        'transfer_note',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
            'unassigned_at' => 'datetime',
        ];
    }

    // -------------------------------------------------------------------------
    // Relations
    // -------------------------------------------------------------------------

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function assignedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_by');
    }
}

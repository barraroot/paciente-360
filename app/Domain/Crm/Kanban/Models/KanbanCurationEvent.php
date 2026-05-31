<?php

declare(strict_types=1);

namespace App\Domain\Crm\Kanban\Models;

use App\Domain\Ai\Execution\Models\AiToolInvocation;
use App\Models\Concerns\BelongsToTenant;
use App\Models\FunilColuna;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feature 018 (US3, FR-022) — registro auditável de cada mutação automática
 * feita no card pela IA (tool MCP) ou por listener.
 *
 * Cobre transições de status (event_kind = lead_created, slot_held, etc.)
 * E updates de perfil (event_kind = profile_updated, note_added) E
 * supressões sob FR-020 (applied=false, suppression_reason).
 *
 * tool_invocation_id correlaciona com ai_tool_invocations (Fase 17 reusada)
 * quando a origem é uma capability MCP.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $paciente_id
 * @property string $event_kind
 * @property string $source ia_tool|auto_listener|manual_override_blocked
 * @property int|null $from_coluna_id
 * @property int|null $to_coluna_id
 * @property bool $applied
 * @property string|null $suppression_reason
 * @property string|null $field_changed
 * @property string|null $value_before
 * @property string|null $value_after
 * @property int|null $turn_version
 * @property int|null $tool_invocation_id
 * @property string $actor_type ia|system|user
 * @property int|null $actor_id
 * @property string|null $reason
 */
class KanbanCurationEvent extends Model
{
    use BelongsToTenant;

    public $timestamps = false;

    protected $table = 'kanban_curation_events';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'paciente_id',
        'event_kind',
        'source',
        'from_coluna_id',
        'to_coluna_id',
        'applied',
        'suppression_reason',
        'field_changed',
        'value_before',
        'value_after',
        'turn_version',
        'tool_invocation_id',
        'actor_type',
        'actor_id',
        'reason',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'applied' => 'boolean',
            'turn_version' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Paciente, $this>
     */
    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    /**
     * @return BelongsTo<FunilColuna, $this>
     */
    public function fromColuna(): BelongsTo
    {
        return $this->belongsTo(FunilColuna::class, 'from_coluna_id');
    }

    /**
     * @return BelongsTo<FunilColuna, $this>
     */
    public function toColuna(): BelongsTo
    {
        return $this->belongsTo(FunilColuna::class, 'to_coluna_id');
    }

    /**
     * @return BelongsTo<AiToolInvocation, $this>
     */
    public function toolInvocation(): BelongsTo
    {
        return $this->belongsTo(AiToolInvocation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_id');
    }
}

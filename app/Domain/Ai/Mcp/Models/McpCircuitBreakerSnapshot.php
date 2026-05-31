<?php

declare(strict_types=1);

namespace App\Domain\Ai\Mcp\Models;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Feature 018 (US7, FR-053b/c/d) — snapshot histórico de transições do
 * circuit breaker do MCP. Analytics + auditoria.
 *
 * O estado VIVO é Redis (mcp:cb:*) — esta tabela registra cada transição
 * (Listener PersistMcpCircuitSnapshotListener — T054).
 *
 * `source` distingue:
 *   - automatic — disparado pelo McpCircuitBreaker (3 falhas em 30s, etc.)
 *   - manual_flag — admin mudou AI_TOOLS_VIA_MCP=false (rollback operacional)
 *
 * SEM tenant_id — é estado global do MCP (1 servidor por instalação).
 * Retenção: 90 dias.
 *
 * @property int $id
 * @property string $transition_to open|half_open|closed
 * @property int $failures_observed
 * @property int $cooldown_seconds
 * @property string|null $last_error_code
 * @property string|null $last_error_message
 * @property string $source automatic|manual_flag
 * @property int|null $actor_user_id
 */
class McpCircuitBreakerSnapshot extends Model
{
    public $timestamps = false;

    protected $table = 'mcp_circuit_breaker_snapshots';

    /** @var array<int, string> */
    protected $fillable = [
        'transition_to',
        'failures_observed',
        'cooldown_seconds',
        'last_error_code',
        'last_error_message',
        'source',
        'actor_user_id',
        'created_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'failures_observed' => 'integer',
            'cooldown_seconds' => 'integer',
            'created_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}

<?php

namespace App\Models;

use App\Exceptions\AuditLogIsImmutableException;
use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Model `AuditLog` — registro imutável de auditoria (FR-035).
 *
 * Tabela: `audit_logs` (schema em data-model.md § 9).
 *
 * Imutabilidade em duas camadas:
 *  1. Model boot: `updating` e `deleting` lançam `AuditLogIsImmutableException`.
 *  2. Trigger PostgreSQL `audit_logs_immutable()`: defesa em profundidade
 *     contra acesso direto via `DB::table()`.
 *
 * NÃO usa `BelongsToTenant` — auditoria precisa funcionar sem global scope
 * para que o Super Admin enxergue entradas cross-tenant. Filtragem por tenant
 * é responsabilidade do `AuditService`.
 *
 * @property int $id
 * @property int|null $tenant_id
 * @property int|null $user_id
 * @property string $actor_type 'user' | 'system' | 'webhook'
 * @property string $action
 * @property string|null $auditable_type
 * @property int|null $auditable_id
 * @property array<string, mixed> $payload
 * @property string|null $ip
 * @property string|null $user_agent
 * @property string|null $request_id
 * @property Carbon $created_at
 *
 * @see specs/001-fundacao-multitenant/data-model.md § 9
 * @see App\Services\Audit\AuditService
 */
class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /**
     * Desabilita `updated_at` — audit_logs é append-only, sem updated_at.
     */
    public const UPDATED_AT = null;

    protected $table = 'audit_logs';

    /** @var array<int, never> */
    protected $guarded = [];

    /**
     * Boot: bloqueia update e delete no nível do Model.
     */
    protected static function boot(): void
    {
        parent::boot();

        static::updating(static function (): void {
            throw new AuditLogIsImmutableException('update');
        });

        static::deleting(static function (): void {
            throw new AuditLogIsImmutableException('delete');
        });
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'created_at' => 'immutable_datetime',
            'actor_type' => 'string',
        ];
    }

    /**
     * Usuário ator (eager loadable). Null quando `actor_type !== 'user'`.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withTrashed();
    }

    /**
     * Verifica se a ação foi executada por um usuário autenticado.
     */
    public function isUserAction(): bool
    {
        return $this->actor_type === 'user';
    }

    /**
     * Verifica se a ação foi executada pelo sistema (job, webhook, CLI).
     */
    public function isSystemAction(): bool
    {
        return $this->actor_type === 'system';
    }
}

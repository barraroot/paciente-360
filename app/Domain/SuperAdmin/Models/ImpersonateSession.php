<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Models;

use App\Models\Tenant;
use App\Models\User;
use Database\Factories\ImpersonateSessionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * **T089 (Fase 8 — Lote B US-12.1)** — Sessão de impersonate (Q19).
 *
 * Tabela GLOBAL — Super Admin não pertence a tenant. PARTIAL UNIQUE
 * `(super_admin_id) WHERE ended_at IS NULL` impede 2 sessões simultâneas
 * pelo mesmo SA.
 *
 * @property int $id
 * @property int $super_admin_id
 * @property int $tenant_id
 * @property Carbon $started_at
 * @property Carbon|null $ended_at
 * @property int|null $duration_seconds
 * @property string $scope
 * @property string $ip_address
 * @property string|null $user_agent
 * @property int $screens_visited_count
 * @property string $reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User $superAdmin
 * @property-read Tenant $tenant
 */
class ImpersonateSession extends Model
{
    /** @use HasFactory<ImpersonateSessionFactory> */
    use HasFactory;

    protected $table = 'impersonate_sessions';

    protected $fillable = [
        'super_admin_id',
        'tenant_id',
        'started_at',
        'ended_at',
        'duration_seconds',
        'scope',
        'ip_address',
        'user_agent',
        'screens_visited_count',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'ended_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ImpersonateSessionFactory
    {
        return ImpersonateSessionFactory::new();
    }

    // ─── Relations ───────────────────────────────────────────────────────────

    public function superAdmin(): BelongsTo
    {
        return $this->belongsTo(User::class, 'super_admin_id');
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function auditScreens(): HasMany
    {
        return $this->hasMany(SuperAdminAuditScreen::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('ended_at');
    }

    public function scopeBySuperAdmin(Builder $query, int $userId): Builder
    {
        return $query->where('super_admin_id', $userId);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->ended_at === null;
    }

    protected function duration(): Attribute
    {
        return Attribute::get(function (): ?int {
            if ($this->duration_seconds !== null) {
                return $this->duration_seconds;
            }

            if ($this->ended_at !== null) {
                return (int) $this->ended_at->diffInSeconds($this->started_at, true);
            }

            return null;
        });
    }
}

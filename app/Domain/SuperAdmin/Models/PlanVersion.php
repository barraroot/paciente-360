<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Models;

use App\Casts\AsJsonArray;
use App\Models\Plan;
use App\Models\User;
use Database\Factories\PlanVersionFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * **T119 (Fase 8 — Lote B US-12.2)** — Snapshot versionado de um plano comercial (Q12.2.2).
 *
 * Cada edição de plano cria nova versão. Tenants existentes ficam vinculados
 * à versão original via {@see TenantPlanBinding}.
 *
 * **Tabela GLOBAL** (sem `tenant_id`) — catálogo de plataforma.
 *
 * @property int $id
 * @property int $plan_id
 * @property int $version
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property array<string, mixed> $snapshot
 * @property int|null $created_by_user_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Plan $plan
 * @property-read User|null $createdBy
 */
class PlanVersion extends Model
{
    /** @use HasFactory<PlanVersionFactory> */
    use HasFactory;

    protected $table = 'plan_versions';

    protected $fillable = [
        'plan_id',
        'version',
        'valid_from',
        'valid_to',
        'snapshot',
        'created_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'valid_from' => 'datetime',
            'valid_to' => 'datetime',
            'snapshot' => AsJsonArray::class,
        ];
    }

    protected static function newFactory(): PlanVersionFactory
    {
        return PlanVersionFactory::new();
    }

    // ─── Relations ───────────────────────────────────────────────────────────

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function tenantBindings(): HasMany
    {
        return $this->hasMany(TenantPlanBinding::class);
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    /**
     * Versões vigentes (valid_to IS NULL).
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('valid_to');
    }

    public function scopeForPlan(Builder $query, int $planId): Builder
    {
        return $query->where('plan_id', $planId);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->valid_to === null;
    }

    /**
     * Lookup tipado dos limites do snapshot. Reusa fallback de
     * {@see \App\Models\Concerns\HasTenantPlanLimits} se a chave não existir.
     */
    public function limit(string $key): ?int
    {
        $value = $this->snapshot[$key] ?? null;

        return is_int($value) ? $value : null;
    }
}

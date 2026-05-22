<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Models;

use App\Models\Tenant;
use App\Models\User;
use Database\Factories\TenantPlanBindingFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T119 (Fase 8 — Lote B US-12.2)** — Liga tenant a uma versão específica do plano (Q12.2.2).
 *
 * Histórico imutável: cada alteração de plano fecha o vínculo atual
 * (`effective_to = now()`) e cria novo. PARTIAL UNIQUE impede 2 bindings
 * vigentes no mesmo tenant.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $plan_version_id
 * @property Carbon $effective_from
 * @property Carbon|null $effective_to
 * @property int|null $changed_by_user_id
 * @property string|null $change_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read PlanVersion $planVersion
 * @property-read User|null $changedBy
 */
class TenantPlanBinding extends Model
{
    /** @use HasFactory<TenantPlanBindingFactory> */
    use HasFactory;

    protected $table = 'tenant_plan_bindings';

    protected $fillable = [
        'tenant_id',
        'plan_version_id',
        'effective_from',
        'effective_to',
        'changed_by_user_id',
        'change_reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'effective_to' => 'datetime',
        ];
    }

    protected static function newFactory(): TenantPlanBindingFactory
    {
        return TenantPlanBindingFactory::new();
    }

    // ─── Relations ───────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function planVersion(): BelongsTo
    {
        return $this->belongsTo(PlanVersion::class);
    }

    public function changedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'changed_by_user_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('effective_to');
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function isActive(): bool
    {
        return $this->effective_to === null;
    }
}

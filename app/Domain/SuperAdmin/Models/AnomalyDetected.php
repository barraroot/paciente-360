<?php

declare(strict_types=1);

namespace App\Domain\SuperAdmin\Models;

use App\Casts\AsJsonArray;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\AnomalyDetectedFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T131 (Fase 8 — Lote B US-12.3)** — Anomalia detectada pelo cron `super-admin:detect-anomalies`.
 *
 * Tabela GLOBAL — anomalias podem ser por tenant específico OU sistêmicas
 * (tenant_id = NULL para queda de conversão sistêmica).
 *
 * @property int $id
 * @property AnomalyCategory $categoria
 * @property int|null $tenant_id
 * @property AnomalySeverity $severity
 * @property array<string, mixed> $threshold_breached
 * @property Carbon $detected_at
 * @property array<int, string> $notified_via
 * @property Carbon|null $acknowledged_at
 * @property int|null $acknowledged_by_user_id
 * @property Carbon|null $resolved_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant|null $tenant
 * @property-read User|null $acknowledgedBy
 */
class AnomalyDetected extends Model
{
    /** @use HasFactory<AnomalyDetectedFactory> */
    use HasFactory;

    protected $table = 'anomalies_detected';

    protected $fillable = [
        'categoria',
        'tenant_id',
        'severity',
        'threshold_breached',
        'detected_at',
        'notified_via',
        'acknowledged_at',
        'acknowledged_by_user_id',
        'resolved_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'categoria' => AnomalyCategory::class,
            'severity' => AnomalySeverity::class,
            'threshold_breached' => AsJsonArray::class,
            'notified_via' => AsJsonArray::class,
            'detected_at' => 'datetime',
            'acknowledged_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }

    protected static function newFactory(): AnomalyDetectedFactory
    {
        return AnomalyDetectedFactory::new();
    }

    // ─── Relations ───────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function acknowledgedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'acknowledged_by_user_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereNull('resolved_at');
    }

    public function scopeUnacknowledged(Builder $query): Builder
    {
        return $query->whereNull('acknowledged_at');
    }

    public function scopeCritical(Builder $query): Builder
    {
        return $query->where('severity', AnomalySeverity::Critical->value);
    }

    public function scopeByCategoria(Builder $query, AnomalyCategory|string $categoria): Builder
    {
        $value = $categoria instanceof AnomalyCategory ? $categoria->value : $categoria;

        return $query->where('categoria', $value);
    }

    public function scopeForTenant(Builder $query, int $tenantId): Builder
    {
        return $query->where('tenant_id', $tenantId);
    }

    /**
     * Anomalias da mesma categoria + tenant nos últimos N minutos.
     * Usado para enforce de cooldown (Q22 — 30min default).
     */
    public function scopeWithinCooldown(Builder $query, AnomalyCategory|string $categoria, ?int $tenantId, int $cooldownMinutes): Builder
    {
        $value = $categoria instanceof AnomalyCategory ? $categoria->value : $categoria;
        $cutoff = Carbon::now()->subMinutes($cooldownMinutes);

        return $query
            ->where('categoria', $value)
            ->where('detected_at', '>=', $cutoff)
            ->when($tenantId === null, fn ($q) => $q->whereNull('tenant_id'))
            ->when($tenantId !== null, fn ($q) => $q->where('tenant_id', $tenantId));
    }
}

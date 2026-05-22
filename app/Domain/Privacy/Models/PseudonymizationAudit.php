<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

use App\Casts\AsJsonArray;
use App\Models\User;
use Database\Factories\PseudonymizationAuditFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T071 (Fase 8 — Lote A US-13.3)** — Audit de pseudonimização (GLOBAL).
 *
 * Tabela NÃO usa `BelongsToTenant` — auditoria é exercício de plataforma.
 *
 * @property int $id
 * @property Carbon $audited_at
 * @property int|null $audited_by_user_id
 * @property PseudonymizationAuditMode $mode
 * @property list<string> $scope_event_types
 * @property int|null $sample_size
 * @property int $total_events_scanned
 * @property int $non_conformant_events
 * @property list<array<string, mixed>>|null $findings
 * @property string|null $report_summary
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read User|null $auditedBy
 */
class PseudonymizationAudit extends Model
{
    /** @use HasFactory<PseudonymizationAuditFactory> */
    use HasFactory;

    protected $table = 'pseudonymization_audits';

    protected $fillable = [
        'audited_at',
        'audited_by_user_id',
        'mode',
        'scope_event_types',
        'sample_size',
        'total_events_scanned',
        'non_conformant_events',
        'findings',
        'report_summary',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'audited_at' => 'datetime',
            'mode' => PseudonymizationAuditMode::class,
            'scope_event_types' => AsJsonArray::class,
            'findings' => AsJsonArray::class,
        ];
    }

    protected static function newFactory(): PseudonymizationAuditFactory
    {
        return PseudonymizationAuditFactory::new();
    }

    public function auditedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'audited_by_user_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeByMode(Builder $query, PseudonymizationAuditMode|string $mode): Builder
    {
        $value = $mode instanceof PseudonymizationAuditMode ? $mode->value : $mode;

        return $query->where('mode', $value);
    }

    public function scopeWithFindings(Builder $query): Builder
    {
        return $query->where('non_conformant_events', '>', 0);
    }

    public function scopeRecent(Builder $query, int $days = 30): Builder
    {
        return $query->where('audited_at', '>=', Carbon::now()->subDays($days));
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function hasFindings(): bool
    {
        return $this->non_conformant_events > 0;
    }

    public function isCompliant(): bool
    {
        return $this->non_conformant_events === 0;
    }
}

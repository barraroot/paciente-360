<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\PortabilityRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T043 (Fase 8 — Lote A US-13.2)** — Model de Portabilidade de Dados (Q28).
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $patient_id
 * @property Carbon $requested_at
 * @property Carbon $deadline_at
 * @property PortabilityStatus $status
 * @property Carbon|null $executed_at
 * @property int|null $executed_by_user_id
 * @property string|null $file_path
 * @property int|null $file_size_bytes
 * @property string|null $file_signed_url_id
 * @property Carbon|null $url_expires_at
 * @property Carbon|null $downloaded_at
 * @property string $schema_version
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Paciente $patient
 * @property-read Tenant $tenant
 * @property-read User|null $executedBy
 */
class PortabilityRequest extends Model
{
    /** @use HasFactory<PortabilityRequestFactory> */
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'portability_requests';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'requested_at',
        'deadline_at',
        'status',
        'executed_at',
        'executed_by_user_id',
        'file_path',
        'file_size_bytes',
        'file_signed_url_id',
        'url_expires_at',
        'downloaded_at',
        'schema_version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'requested_at' => 'datetime',
            'deadline_at' => 'datetime',
            'executed_at' => 'datetime',
            'url_expires_at' => 'datetime',
            'downloaded_at' => 'datetime',
            'status' => PortabilityStatus::class,
        ];
    }

    protected static function newFactory(): PortabilityRequestFactory
    {
        return PortabilityRequestFactory::new();
    }

    // ─── Relations ───────────────────────────────────────────────────────────

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'patient_id');
    }

    public function executedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'executed_by_user_id');
    }

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeOpen(Builder $query): Builder
    {
        return $query->whereIn('status', [
            PortabilityStatus::Open->value,
            PortabilityStatus::Generating->value,
        ]);
    }

    public function scopeReady(Builder $query): Builder
    {
        return $query->where('status', PortabilityStatus::Ready);
    }

    public function scopeNearingDeadline(Builder $query, int $daysAhead): Builder
    {
        $cutoff = Carbon::now()->addDays($daysAhead);

        return $query->open()
            ->where('deadline_at', '<=', $cutoff);
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function signedUrlExpired(): bool
    {
        return $this->url_expires_at !== null && $this->url_expires_at->isPast();
    }

    public function daysUntilDeadline(): int
    {
        return max(0, (int) Carbon::now()->diffInDays($this->deadline_at, false));
    }
}

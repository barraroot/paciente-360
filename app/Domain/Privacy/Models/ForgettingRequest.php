<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\ForgettingRequestFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T042 (Fase 8 — Lote A US-13.2)** — Model da solicitação de Direito ao Esquecimento.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $patient_id
 * @property Carbon $requested_at
 * @property Carbon $deadline_at
 * @property string $channel_of_request
 * @property ForgettingStatus $status
 * @property Carbon|null $executed_at
 * @property int|null $executed_by_user_id
 * @property array<string, mixed>|null $fields_anonymized
 * @property array<string, mixed>|null $fields_deleted
 * @property array<int, array{field: string, reason: string, retention_days: int}>|null $fields_preserved_reason
 * @property string|null $denial_reason
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Paciente $patient
 * @property-read Tenant $tenant
 * @property-read User|null $executedBy
 */
class ForgettingRequest extends Model
{
    /** @use HasFactory<ForgettingRequestFactory> */
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'forgetting_requests';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'requested_at',
        'deadline_at',
        'channel_of_request',
        'status',
        'executed_at',
        'executed_by_user_id',
        'fields_anonymized',
        'fields_deleted',
        'fields_preserved_reason',
        'denial_reason',
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
            'status' => ForgettingStatus::class,
            'fields_anonymized' => AsJsonArray::class,
            'fields_deleted' => AsJsonArray::class,
            'fields_preserved_reason' => AsJsonArray::class,
        ];
    }

    protected static function newFactory(): ForgettingRequestFactory
    {
        return ForgettingRequestFactory::new();
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
            ForgettingStatus::Open->value,
            ForgettingStatus::PendingVerification->value,
            ForgettingStatus::InProgress->value,
        ]);
    }

    public function scopeExecuted(Builder $query): Builder
    {
        return $query->where('status', ForgettingStatus::Executed);
    }

    /**
     * Solicitações cujo deadline está dentro dos próximos N dias úteis.
     */
    public function scopeNearingDeadline(Builder $query, int $daysAhead): Builder
    {
        $cutoff = Carbon::now()->addDays($daysAhead);

        return $query->open()
            ->where('deadline_at', '<=', $cutoff);
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->open()
            ->where('deadline_at', '<', Carbon::now());
    }

    // ─── Helpers ─────────────────────────────────────────────────────────────

    public function daysUntilDeadline(): int
    {
        return max(0, (int) Carbon::now()->diffInDays($this->deadline_at, false));
    }
}

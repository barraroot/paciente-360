<?php

declare(strict_types=1);

namespace App\Domain\Privacy\Models;

use App\Casts\AsJsonArray;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\Tenant;
use Database\Factories\ConsentRecordFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * **T022 (Fase 8 — Lote A US-13.1)** — Model do consentimento LGPD.
 *
 * Imutável em prática: novos consentimentos = nova linha. Revogar cria
 * nova linha state=revoked + revoked_at; PARTIAL UNIQUE garante que
 * apenas 1 linha state='granted' existe por (patient, finalidade) ativa.
 *
 * @property int $id
 * @property int $tenant_id
 * @property int $patient_id
 * @property string $channel
 * @property ConsentFinalidade $finalidade
 * @property ConsentState $state
 * @property Carbon|null $granted_at
 * @property Carbon|null $revoked_at
 * @property int|null $evidence_message_id
 * @property array<string, mixed>|null $evidence_snapshot
 * @property string $terms_version
 * @property array<string, mixed>|null $scope
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Tenant $tenant
 * @property-read Paciente $patient
 */
class ConsentRecord extends Model
{
    /** @use HasFactory<ConsentRecordFactory> */
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'consent_records';

    protected $fillable = [
        'tenant_id',
        'patient_id',
        'channel',
        'finalidade',
        'state',
        'granted_at',
        'revoked_at',
        'evidence_message_id',
        'evidence_snapshot',
        'terms_version',
        'scope',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'finalidade' => ConsentFinalidade::class,
            'state' => ConsentState::class,
            'granted_at' => 'datetime',
            'revoked_at' => 'datetime',
            'evidence_snapshot' => AsJsonArray::class,
            'scope' => AsJsonArray::class,
        ];
    }

    protected static function newFactory(): ConsentRecordFactory
    {
        return ConsentRecordFactory::new();
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

    // ─── Scopes ──────────────────────────────────────────────────────────────

    public function scopeGranted(Builder $query): Builder
    {
        return $query->where('state', ConsentState::Granted);
    }

    public function scopeRevoked(Builder $query): Builder
    {
        return $query->where('state', ConsentState::Revoked);
    }

    public function scopeRefused(Builder $query): Builder
    {
        return $query->where('state', ConsentState::Refused);
    }

    public function scopeForFinalidade(Builder $query, ConsentFinalidade|string $finalidade): Builder
    {
        $value = $finalidade instanceof ConsentFinalidade ? $finalidade->value : $finalidade;

        return $query->where('finalidade', $value);
    }

    public function scopeForPatient(Builder $query, int $patientId): Builder
    {
        return $query->where('patient_id', $patientId);
    }

    // ─── Accessors ───────────────────────────────────────────────────────────

    protected function isActive(): Attribute
    {
        return Attribute::get(fn (): bool => $this->state === ConsentState::Granted);
    }
}

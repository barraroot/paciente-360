<?php

namespace App\Domain\Prescription\Prescription;

use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\PrescriptionItem\PrescriptionItem;
use App\Domain\Prescription\Renewal\PrescriptionRenewal;
use App\Models\Agenda\Appointment;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\Prescription\PrescriptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

class Prescription extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'prescriptions';

    /** @var array<int, string> */
    protected $fillable = [
        'patient_id',
        'professional_id',
        'appointment_id',
        'type',
        'status',
        'issued_at',
        'expires_at',
        'renewed_from_id',
        'source',
        'cancelled_at',
        'cancelled_by_user_id',
        'cancellation_reason_category',
        'cancellation_reason',
        'alert_disabled',
        'notes',
        'pdf_path',
        'pdf_version',
        'imported_at',
        'imported_source',
        'historical_external_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PrescriptionType::class,
            'status' => PrescriptionStatus::class,
            'source' => PrescriptionSource::class,
            'cancellation_reason_category' => CancellationReasonCategory::class,
            'issued_at' => 'date',
            'expires_at' => 'date',
            'cancelled_at' => 'datetime',
            'alert_disabled' => 'boolean',
            'notes' => 'encrypted',
            'pdf_version' => 'integer',
            'imported_at' => 'datetime',
        ];
    }

    protected static function newFactory(): PrescriptionFactory
    {
        return PrescriptionFactory::new();
    }

    protected static function booted(): void
    {
        // Global scope: remove do resultado receitas controladas de OUTROS emissores
        // apenas quando o user não tem `prescription.view_controlled`.
        //
        // ATENÇÃO: Segundo Q8a da spec, receitas controladas NÃO devem ser omitidas
        // da lista — devem aparecer como "linha mascarada". O mascaramento ocorre no
        // PrescriptionResource via ControlledPrescriptionMaskingService.
        //
        // O scope abaixo é removido para garantir que receitas controladas apareçam
        // na lista para todos os usuários com prescription.view, mas com conteúdo
        // clínico mascarado no Resource. Desta forma:
        //  - list: retorna todas (mascaradas para não-emissores sem view_controlled)
        //  - show: retorna 200 (mascarado) — não 404 — para coordenação operacional
        //
        // Referência: specs/007-gestao-receituario/spec.md Q8a, Q8b
    }

    /**
     * Verifica se a receita está vencida (expires_at < hoje).
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isBefore(Carbon::today());
    }

    /**
     * Verifica se a receita está cancelada.
     */
    public function isCancelled(): bool
    {
        return $this->status === PrescriptionStatus::Cancelled;
    }

    /**
     * Criticidade visual baseada na proximidade do vencimento.
     * - green: expires_at > hoje + 15d
     * - yellow: hoje <= expires_at <= hoje + 15d
     * - red: expires_at < hoje
     */
    public function criticality(): string
    {
        if ($this->expires_at === null) {
            return 'green';
        }

        $today = Carbon::today();
        $expiresAt = $this->expires_at->copy()->startOfDay();

        if ($expiresAt->isBefore($today)) {
            return 'red';
        }

        $daysUntilExpiry = $today->diffInDays($expiresAt, true);

        if ($daysUntilExpiry <= 15) {
            return 'yellow';
        }

        return 'green';
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class, 'tenant_id');
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'patient_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function cancelledBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'cancelled_by_user_id');
    }

    public function renewedFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'renewed_from_id');
    }

    public function renewals(): HasMany
    {
        return $this->hasMany(PrescriptionRenewal::class, 'original_prescription_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(PrescriptionItem::class);
    }

    public function alerts(): HasMany
    {
        return $this->hasMany(PrescriptionAlert::class);
    }
}

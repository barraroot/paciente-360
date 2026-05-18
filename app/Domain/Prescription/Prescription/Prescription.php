<?php

namespace App\Domain\Prescription\Prescription;

use App\Domain\Prescription\Alert\PrescriptionAlert;
use App\Domain\Prescription\PrescriptionItem\PrescriptionItem;
use App\Domain\Prescription\Renewal\PrescriptionRenewal;
use App\Models\Agenda\Appointment;
use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;

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

    protected static function booted(): void
    {
        static::addGlobalScope('withControlledIfAble', function (Builder $query): void {
            $user = Auth::user();

            if (! $user instanceof User || $user->can('prescription.view_controlled')) {
                return;
            }

            $query->where(function (Builder $builder) use ($user): void {
                $builder->where('type', '!=', PrescriptionType::Controlled->value)
                    ->orWhere('professional_id', $user->getAuthIdentifier());
            });
        });
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

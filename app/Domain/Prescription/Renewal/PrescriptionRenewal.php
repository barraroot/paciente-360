<?php

namespace App\Domain\Prescription\Renewal;

use App\Domain\Prescription\Prescription\Prescription;
use App\Models\Agenda\Appointment;
use App\Models\Concerns\BelongsToTenant;
use App\Models\User;
use Database\Factories\Prescription\PrescriptionRenewalFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrescriptionRenewal extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'prescription_renewals';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'original_prescription_id',
        'renewed_prescription_id',
        'initiated_by',
        'appointment_id',
        'requested_by_user_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'initiated_by' => InitiatedByType::class,
        ];
    }

    protected static function newFactory(): PrescriptionRenewalFactory
    {
        return PrescriptionRenewalFactory::new();
    }

    public function originalPrescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class, 'original_prescription_id');
    }

    public function renewedPrescription(): BelongsTo
    {
        return $this->belongsTo(Prescription::class, 'renewed_prescription_id');
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'appointment_id');
    }

    public function requestedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by_user_id');
    }
}

<?php

namespace App\Domain\Prescription\Preferences;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PatientProfessionalPreference extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'patient_professional_preferences';

    /** @var array<int, string> */
    protected $fillable = [
        'tenant_id',
        'patient_id',
        'professional_id',
        'suppress_renewal_notifications',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'suppress_renewal_notifications' => 'boolean',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Paciente::class, 'patient_id');
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(User::class, 'professional_id');
    }
}

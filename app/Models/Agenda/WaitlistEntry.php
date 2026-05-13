<?php

namespace App\Models\Agenda;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use Database\Factories\Agenda\WaitlistEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Lista de espera FIFO sequencial K=1 (clarify nº 8).
 */
class WaitlistEntry extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'waitlist_entries';

    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'position' => 'integer',
        'notified_at' => 'datetime',
        'notified_for_slot_starts_at' => 'datetime',
        'expires_at' => 'datetime',
    ];

    protected static function newFactory(): WaitlistEntryFactory
    {
        return WaitlistEntryFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function paciente(): BelongsTo
    {
        return $this->belongsTo(Paciente::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    public function acceptedAppointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class, 'accepted_appointment_id');
    }

    public function scopeWaiting(Builder $query): Builder
    {
        return $query->where('status', 'waiting');
    }

    public function scopeNotified(Builder $query): Builder
    {
        return $query->where('status', 'notified');
    }
}

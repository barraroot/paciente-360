<?php

namespace App\Models\Agenda;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Histórico de reagendamentos (clarify nº 7 — base para enforce de limite via COUNT).
 */
class AppointmentReschedule extends Model
{
    use BelongsToTenant;

    protected $table = 'appointment_reschedules';

    public $timestamps = false;

    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'starts_at_anterior' => 'datetime',
        'starts_at_novo' => 'datetime',
        'created_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }
}

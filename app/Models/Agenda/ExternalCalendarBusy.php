<?php

namespace App\Models\Agenda;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Professional;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * T145 — ExternalCalendarBusy (clarify nº 10).
 *
 * Eventos externos do Google sub-calendário NÃO viram Appointment.
 * Apenas bloqueiam slot no SlotGeneratorService.
 */
class ExternalCalendarBusy extends Model
{
    use BelongsToTenant;

    protected $table = 'external_calendar_busy';

    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function calendarSyncAccount(): BelongsTo
    {
        return $this->belongsTo(CalendarSyncAccount::class);
    }

    public function scopeCoveringSlot(Builder $query, Carbon $slotStart, Carbon $slotEnd): Builder
    {
        return $query->where('starts_at', '<', $slotEnd)
            ->where('ends_at', '>', $slotStart);
    }
}

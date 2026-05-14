<?php

namespace App\Models\Agenda;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T144 — Mapping Appointment ↔ external_event_id (US-6.7).
 */
class CalendarSyncedEvent extends Model
{
    use BelongsToTenant;

    protected $table = 'calendar_synced_events';

    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function calendarSyncAccount(): BelongsTo
    {
        return $this->belongsTo(CalendarSyncAccount::class);
    }
}

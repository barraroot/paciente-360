<?php

namespace App\Models\Agenda;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Tenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T098 — ConfirmationDispatch (US-6.4 / clarify nº 6).
 *
 * Cada disparo de confirmação (T-24h / T-2h / retry T-30min / 15min_manual_escalation)
 * gera 1 row aqui. UNIQUE(appointment_id, kind) evita duplicação.
 */
class ConfirmationDispatch extends Model
{
    use BelongsToTenant;

    protected $table = 'confirmation_dispatches';

    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'via_ia' => 'boolean',
        'dispatched_at' => 'datetime',
        'response_received_at' => 'datetime',
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

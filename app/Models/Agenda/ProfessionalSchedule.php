<?php

namespace App\Models\Agenda;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Casts\AsArrayObject;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * T035 — Agenda recorrente do profissional (US-6.1, clarify nº 5).
 */
class ProfessionalSchedule extends Model
{
    use BelongsToTenant;

    protected $table = 'professional_schedules';

    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'day_of_week' => 'integer',
        'blocks' => AsArrayObject::class,
        'effective_from' => 'date',
        'effective_until' => 'date',
    ];

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }
}

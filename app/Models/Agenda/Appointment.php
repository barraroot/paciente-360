<?php

namespace App\Models\Agenda;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Paciente;
use App\Models\Professional;
use App\Models\Tenant;
use App\Models\User;
use Database\Factories\Agenda\AppointmentFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Consulta (entidade central — US-6.3..6.7).
 *
 * Status enum: scheduled | confirmed | canceled | realizada | nao_realizada |
 * concluida_sem_registro (clarify nº 7 — sem 'reagendada'; clarify nº 14 — terminal).
 *
 * @property int $id
 * @property int $tenant_id
 * @property string|null $idempotency_key
 * @property int $paciente_id
 * @property int $professional_id
 * @property int $appointment_type_id
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property string $status
 * @property string $channel_origin
 * @property int|null $created_by_user_id
 * @property string $valor_aplicado
 * @property bool $override_block
 * @property string|null $notes encrypted
 */
class Appointment extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'appointments';

    protected $guarded = ['id', 'tenant_id', 'created_at', 'updated_at'];

    protected $casts = [
        'starts_at' => 'datetime',
        'ends_at' => 'datetime',
        'canceled_at' => 'datetime',
        'confirmed_at' => 'datetime',
        'attendance_marked_at' => 'datetime',
        'auto_flagged_at' => 'datetime',
        'valor_aplicado' => 'decimal:2',
        'override_block' => 'boolean',
        'notes' => 'encrypted',
    ];

    public const ACTIVE_STATUSES = ['scheduled', 'confirmed'];

    public const TERMINAL_STATUSES = ['canceled', 'realizada', 'nao_realizada', 'concluida_sem_registro'];

    protected static function newFactory(): AppointmentFactory
    {
        return AppointmentFactory::new();
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

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    public function reschedules(): HasMany
    {
        return $this->hasMany(AppointmentReschedule::class);
    }

    public function confirmationDispatches(): HasMany
    {
        return $this->hasMany(ConfirmationDispatch::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereIn('status', self::ACTIVE_STATUSES);
    }

    public function getRescheduleCountAttribute(): int
    {
        return $this->reschedules()->count();
    }
}

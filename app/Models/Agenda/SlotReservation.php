<?php

namespace App\Models\Agenda;

use App\Models\Concerns\BelongsToTenant;
use App\Models\Professional;
use App\Models\Tenant;
use Database\Factories\Agenda\SlotReservationFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Reserva pessimista soft de slot (clarify nº 2 / R4).
 */
class SlotReservation extends Model
{
    use BelongsToTenant;
    use HasFactory;

    protected $table = 'slot_reservations';

    public $timestamps = false;

    protected $guarded = ['id', 'tenant_id'];

    protected $casts = [
        'starts_at' => 'datetime',
        'acquired_at' => 'datetime',
        'expires_at' => 'datetime',
        'released_at' => 'datetime',
    ];

    protected static function newFactory(): SlotReservationFactory
    {
        return SlotReservationFactory::new();
    }

    public function tenant(): BelongsTo
    {
        return $this->belongsTo(Tenant::class);
    }

    public function professional(): BelongsTo
    {
        return $this->belongsTo(Professional::class);
    }

    public function appointmentType(): BelongsTo
    {
        return $this->belongsTo(AppointmentType::class);
    }

    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('released_at')->where('expires_at', '>', now());
    }

    public function scopeExpired(Builder $query): Builder
    {
        return $query->whereNull('released_at')->where('expires_at', '<=', now());
    }
}

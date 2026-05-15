<?php

namespace App\Models\Agenda;

use Illuminate\Database\Eloquent\Relations\Pivot;

/**
 * T037 — Pivot M2M tipo↔profissional (carrega tenant_id denormalizado para gate Princípio II).
 */
class AppointmentTypeProfessional extends Pivot
{
    protected $table = 'appointment_type_professional';

    public $incrementing = false;

    public $timestamps = false;

    protected $casts = [
        'created_at' => 'datetime',
    ];
}

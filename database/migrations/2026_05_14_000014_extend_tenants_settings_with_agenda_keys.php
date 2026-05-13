<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * T019 — Backfill `tenants.settings.agenda` com 8 chaves default (clarify nº 1-15).
 *
 * jsonb_set merge das chaves de configuração de agenda. Idempotente — só sobrescreve
 * se chave 'agenda' não existe ou está null.
 */
return new class extends Migration
{
    private const DEFAULT_AGENDA = [
        'min_cancellation_hours' => 4,                  // clarify nº 3
        'max_reschedules_per_appointment' => 2,         // clarify nº 7
        'waitlist_confirmation_minutes' => 15,          // clarify nº 8
        'calendar_sync_window_days' => 60,              // clarify nº 10
        'slot_reservation_ttl_user_minutes' => 5,       // clarify nº 2
        'slot_reservation_ttl_ia_minutes' => 2,         // clarify nº 2
        'auto_close_stale_appointments_days' => 7,      // clarify nº 14
        'attendance_revert_window_hours' => 48,         // clarify nº 14
    ];

    public function up(): void
    {
        $defaultsJson = json_encode((object) self::DEFAULT_AGENDA, JSON_THROW_ON_ERROR);

        DB::statement("
            UPDATE tenants
            SET settings = jsonb_set(
                COALESCE(settings, '{}'::jsonb),
                '{agenda}',
                ?::jsonb,
                true
            )
            WHERE settings -> 'agenda' IS NULL
        ", [$defaultsJson]);
    }

    public function down(): void
    {
        DB::statement("
            UPDATE tenants
            SET settings = settings - 'agenda'
            WHERE settings -> 'agenda' IS NOT NULL
        ");
    }
};
